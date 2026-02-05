<!DOCTYPE html>
<html lang="km">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    @php
        $brandName =
            $appName ?? null ?:
            (function_exists('get_setting')
                ? get_setting('shop_name', null)
                : null) ?:
            config('app.name');
    @endphp
    <title>{{ $brandName }} — OTP</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f5f6fa;
            color: #111827;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Khmer OS Battambang", "Battambang", sans-serif;
            line-height: 1.6;
        }

        .wrap {
            max-width: 640px;
            margin: 0 auto;
            padding: 24px
        }

        .card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(17, 24, 39, .06);
            overflow: hidden;
            border: 1px solid #eee
        }

        /* removed gradient + colors */
        .header {
            background: #ffffff;
            color: #111827;
            padding: 22px 22px 10px 22px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb
        }

        .brand {
            font-weight: 900;
            font-size: 24px;
            margin: 0 0 6px 0
        }

        .title {
            font-size: 18px;
            font-weight: 800;
            margin: 8px 0 0 0
        }

        .content {
            padding: 22px
        }

        .code {
            font-size: 28px;
            letter-spacing: 6px;
            font-weight: 900;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 14px;
            text-align: center
        }

        /* removed blue note colors */
        .note {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px;
            margin: 14px 0;
            color: #374151
        }

        .footer {
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            padding: 16px 22px;
            font-size: 12px;
            text-align: center
        }

        .footer a {
            color: #111827;
            text-decoration: underline
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="header">
                <h1 class="brand">{{ $brandName }}</h1>
                <div class="title">
                    {{ $context === 'register' ? 'កូដសម្រាប់កំណត់ពាក្យសម្ងាត់ដំបូង (OTP)' : 'កូដសម្រាប់កំណត់ពាក្យសម្ងាត់ឡើងវិញ (OTP)' }}
                </div>
            </div>

            <div class="content">
                <p>សួស្តី {{ $user->name ?? 'អ្នកប្រើប្រាស់' }},</p>
                <p>កូដរបស់អ្នកគឺ៖</p>
                <div class="code">{{ $code }}</div>
                <p class="note">
                    កូដមានសុពលភាព {{ $ttlMinutes }} នាទី។ កុំចែករំលែកកូដនេះជាមួយនរណាម្នាក់។
                </p>
                <p>សូមវាយបញ្ចូលកូដនេះ នៅលើទំព័រ «Reset / Set Password (OTP)» របស់យើង។</p>
            </div>

            <div class="footer">
                ត្រូវការជំនួយ? <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
            </div>
        </div>
    </div>
</body>

</html>
