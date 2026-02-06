<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Konthaina\Khqr\KHQRGenerator;
use App\Models\BakongTransaction;
use App\Models\Setting;

class BakongController extends Controller
{
    /** ===== Helpers: Config / Settings / Token ===== */

    private function baseUrl(): string
    {
        // config('bakong.base_url') if you created config/bakong.php, else fallback to env
        $url = config('bakong.base_url', env('BAKONG_BASE_URL', 'https://api-bakong.nbc.gov.kh/'));
        return rtrim($url, '/');
    }

    private function fixedToken(): string
    {
        // Prefer cached token; refresh with getToken() endpoint or read from config/env here
        return Cache::remember('bakong_token', now()->addMinutes(55), function () {
            return config('bakong.fixed_token', env('BAKONG_FIXED_TOKEN', ''));
        });
    }

    private function merchantId(): string
    {
        // Read from settings key 'bakong_machine_id' first; fallback to config/env
        $db = optional(Setting::where('key', 'bakong_machine_id')->first())->value;
        return $db !== null && $db !== ''
            ? $db
            : config('bakong.merchant_id', env('BAKONG_MERCHANT_ID', ''));
    }

    private function shopName(): string
    {
        $db = optional(Setting::where('key', 'shop_name')->first())->value;
        return $db ?: config('app.name', 'Cafe Eden');
    }

    /** ===== Public endpoints ===== */

    // Keeps your explicit token-refresh endpoint if you want to force-refresh cache
    public function getToken()
    {
        $token = config('bakong.fixed_token', env('BAKONG_FIXED_TOKEN', ''));
        Cache::put('bakong_token', $token, now()->addMinutes(55));
        return response()->json(['token' => $token]);
    }

    public function generateQR(Request $request)
    {
        try {
            $request->validate([
                'amount'   => 'required|numeric|min:0.01',
                'currency' => 'nullable|in:KHR,USD',
            ]);

            $merchantId = $this->merchantId();
            if (!$merchantId) {
                return response()->json([
                    'message' => 'Merchant ID is not configured. Please set bakong_machine_id in Settings.',
                ], 422);
            }

            if (!$this->fixedToken()) {
                return response()->json([
                    'message' => 'Bakong token is not configured.',
                ], 422);
            }

            $currencyInput = strtoupper($request->input('currency', 'KHR'));

            $billNumber = uniqid('txn_');
            $shopName   = $this->shopName();

            // BIC example kept; make configurable if needed (CADIKHPP is CADI bank) -- It's only for MERCHANT_TYPE_MERCHANT, not needed for MERCHANT_TYPE_INDIVIDUAL
            // $bic = config('bakong.bank_bic', 'CADIKHPP');

            $khqr = new KHQRGenerator(KHQRGenerator::MERCHANT_TYPE_INDIVIDUAL);
            $khqr->setBakongAccountId($merchantId);
            // $khqr->setMerchantId($merchantId);
            $khqr->setMerchantName($shopName);
            // $khqr->setAcquiringBank($bic);
            $khqr->setCurrency($currencyInput);
            $khqr->setAmount((float) $request->amount);
            $khqr->setMerchantCity('Phnom Penh');
            $khqr->setBillNumber($billNumber);
            $khqr->setStoreLabel($shopName);
            $khqr->setTerminalLabel(config('bakong.terminal_label', 'POS-01'));

            $result = $khqr->generate();

            if (empty($result['qr'])) {
                return response()->json([
                    'message'       => 'QR Generation Failed',
                    'error'         => $result,
                ], 500);
            }

            $qrString = $result['qr'];
            $md5Hash  = $result['md5'] ?? md5($qrString);

            BakongTransaction::create([
                'bill_number' => $billNumber,
                'amount'      => $request->amount,
                'currency'    => $currencyInput,
                'qr_string'   => $qrString,
                'md5_hash'    => $md5Hash,
                'status'      => 'pending',
            ]);

            return response()->json([
                'qr_string'   => $qrString,
                'md5'         => $md5Hash,
                'bill_number' => $billNumber,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'QR generation error',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyTransactionByMd5()
    {
        try {
            $token  = $this->fixedToken();
            $client = new Client();

            $tx = BakongTransaction::where('status', 'pending')
                ->whereNotNull('md5_hash')
                ->latest('created_at')
                ->first();

            if (!$tx) {
                return response()->json([
                    'message' => 'No pending transactions found.',
                    'result'  => null,
                ]);
            }

            try {
                $res = $client->post($this->baseUrl() . '/v1/check_transaction_by_md5', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept'        => 'application/json',
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'md5' => $tx->md5_hash,
                    ],
                ]);

                $raw  = $res->getBody()->getContents();
                $data = json_decode($raw, true);

                $result = [
                    'bill'         => $tx->bill_number,
                    'md5'          => $tx->md5_hash,
                    'raw_response' => $data,
                    'updated'      => false,
                ];

                if (isset($data['responseCode']) && $data['responseCode'] === 0 && !empty($data['data'])) {
                    $tx->status       = 'success';
                    $tx->completed_at = now();
                    $tx->send_from    = $data['data']['fromAccountId'] ?? null;
                    $tx->receive_to   = $data['data']['toAccountId'] ?? null;
                    $tx->save();
                    $result['updated'] = true;

                    // Alert telegram
                    $this->sendTelegramAlert(
                        "<b>{$this->shopName()} Payment Success</b>\n"
                            . "Bill No: <code>{$tx->bill_number}</code>\n"
                            . "Amount: <b>{$tx->amount} {$tx->currency}</b>\n"
                            . "From: <code>{$tx->send_from}</code>\n"
                            . "To: <code>{$tx->receive_to}</code>\n"
                            . "MD5: <code>{$tx->md5_hash}</code>\n"
                            . "Date & Time: " . now()->format('d M Y g:i A')
                    );
                }

                return response()->json([
                    'message' => '✅ MD5 Verification Complete (latest only)',
                    'result'  => $result,
                ]);
            } catch (\Exception $ex) {
                return response()->json([
                    'message' => '❌ Error verifying latest transaction',
                    'bill'    => $tx->bill_number,
                    'error'   => $ex->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ MD5 verification failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function checkStatus($billNumber)
    {
        try {
            $token  = $this->fixedToken();
            $client = new Client();

            $response = $client->get($this->baseUrl() . "/v1/transactions/status/{$billNumber}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
            ]);

            $data        = json_decode($response->getBody(), true);
            $transaction = BakongTransaction::where('bill_number', $billNumber)->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            if (isset($data['status']) && strtolower($data['status']) === 'success') {
                $transaction->status = 'success';
                $transaction->save();
            }

            return response()->json([
                'message'        => 'Transaction status checked',
                'bakong_status'  => $data['status'] ?? 'unknown',
                'current_status' => $transaction->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Status check failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function handlePushback(Request $request)
    {
        try {
            Log::info('📥 Pushback received', $request->all());

            $data = $request->all();

            if (!isset($data['billNumber']) || !isset($data['status'])) {
                return response()->json(['message' => 'Missing billNumber or status'], 422);
            }

            $transaction = BakongTransaction::where('bill_number', $data['billNumber'])->first();

            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found'], 404);
            }

            $transaction->status = strtolower($data['status']);
            $transaction->save();

            return response()->json([
                'message'      => '✅ Transaction status updated via pushback',
                'bill_number'  => $transaction->bill_number,
                'status'       => $transaction->status,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Pushback failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function verifyTransactionByBill()
    {
        try {
            $token   = $this->fixedToken();
            $client  = new Client();
            $pending = BakongTransaction::where('status', 'pending')->get();

            $updated = 0;
            $results = [];

            foreach ($pending as $tx) {
                try {
                    $res  = $client->get($this->baseUrl() . "/v1/transactions/status/{$tx->bill_number}", [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $token,
                            'Accept'        => 'application/json',
                        ],
                    ]);
                    $body = $res->getBody()->getContents();
                    $data = json_decode($body, true);

                    $results[] = [
                        'bill'     => $tx->bill_number,
                        'response' => $data,
                        'status'   => $data['status'] ?? 'not found',
                    ];

                    if (isset($data['status']) && strtolower($data['status']) === 'success') {
                        $tx->status = 'success';
                        $tx->save();
                        $updated++;
                        $results[count($results) - 1]['updated'] = true;
                    } else {
                        $results[count($results) - 1]['updated'] = false;
                    }
                } catch (\Exception $ex) {
                    $results[] = [
                        'bill'  => $tx->bill_number,
                        'error' => $ex->getMessage(),
                    ];
                }
            }

            return response()->json([
                'message' => "✅ Verified {$updated} transactions by bill_number.",
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => '❌ Bill verification failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /** ===== Telegram Alert ===== */

    private function sendTelegramAlert($message)
    {
        try {
            $botToken = env('TELEGRAM_BOT_TOKEN');
            $chatId   = env('TELEGRAM_CHAT_ID');

            if (!$botToken || !$chatId) {
                return;
            }

            $client = new Client();
            $client->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'form_params' => [
                    'chat_id'    => $chatId,
                    'text'       => $message,
                    'parse_mode' => 'HTML',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error("❌ Telegram alert failed: " . $e->getMessage());
        }
    }
}
