<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>🎉 Happy Birthday!</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>

<body style="
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background-color: #f0fdf4;
    -webkit-text-size-adjust: 100%;
    -ms-text-size-adjust: 100%;
">

    <style>
        /* Email-safe CSS animations */
        @keyframes fadeIn {
            0% {
                opacity: 0;
            }

            100% {
                opacity: 1;
            }
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        @keyframes bounce {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-8px);
            }
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.8;
            }
        }

        @keyframes shimmer {
            0% {
                background-position: -200% center;
            }

            100% {
                background-position: 200% center;
            }
        }

        /* Email client compatibility */
        .fade-in {
            animation: fadeIn 2s ease-out;
        }

        .float-animation {
            animation: float 3s ease-in-out infinite;
        }

        .bounce-animation {
            animation: bounce 2s ease-in-out infinite;
        }

        .pulse-animation {
            animation: pulse 2s ease-in-out infinite;
        }

        .shimmer-effect {
            background: linear-gradient(90deg, transparent 0%, rgba(255, 255, 255, 0.4) 50%, transparent 100%);
            background-size: 200% 100%;
            animation: shimmer 3s infinite;
        }

        /* Responsive styles */
        @media only screen and (max-width: 600px) {
            .mobile-padding {
                padding: 20px !important;
            }

            .mobile-text-large {
                font-size: 28px !important;
            }

            .mobile-text-medium {
                font-size: 18px !important;
            }

            .mobile-text-small {
                font-size: 16px !important;
            }

            .mobile-block {
                display: block !important;
                width: 100% !important;
            }

            .mobile-center {
                text-align: center !important;
            }

            .mobile-hide {
                display: none !important;
            }

            .celebrant-image {
                width: 120px !important;
                height: 120px !important;
            }
        }

        @media only screen and (max-width: 480px) {
            .mobile-padding {
                padding: 15px !important;
            }

            .mobile-text-large {
                font-size: 24px !important;
            }

            .celebrant-image {
                width: 100px !important;
                height: 100px !important;
            }
        }

        /* Outlook specific styles */
        < !--[if mso]>.fade-in,
        .float-animation,
        .bounce-animation,
        .pulse-animation,
        .shimmer-effect {
            animation: none !important;
        }

        < ![endif]-->
    </style>

    <!-- Main Container -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
        style="background-color: #f0fdf4;">
        <tr>
            <td align="center" style="padding: 20px;">

                <!-- Email Content Table -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="fade-in" style="
                    max-width: 600px;
                    width: 100%;
                    background-color: #ffffff;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                    overflow: hidden;
                    border: 1px solid rgba(17, 70, 41, 0.1);
                ">

                    <!-- Header Section -->
                    <tr>
                        <td style="
                            background: linear-gradient(135deg, #114629 0%, #1d5a3f 50%, #2d7a5f 100%);
                            padding: 40px 30px;
                            text-align: center;
                            position: relative;
                        ">
                            <!-- Decorative Elements -->
                            <div style="
                                position: absolute;
                                top: 15px;
                                left: 20px;
                                font-size: 30px;
                                opacity: 0.7;
                            " class="float-animation">🎈</div>

                            <div style="
                                position: absolute;
                                top: 15px;
                                right: 20px;
                                font-size: 25px;
                                opacity: 0.7;
                            " class="bounce-animation">🎉</div>

                            <!-- Celebrant Image -->
                            <div style="margin-bottom: 20px;">
                                @if($PASSPORT_IMAGE)
                                    <img src="{{ $PASSPORT_IMAGE }}" alt="{{ $RECIPIENT_NAME }}" style="
                                            width: 150px;
                                            height: 150px;
                                            border-radius: 50%;
                                            border: 5px solid rgba(255,255,255,0.3);
                                            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                                            display: block;
                                            margin: 0 auto;
                                            object-fit: cover;
                                        ">
                                @endif
                            </div>

                            <!-- Birthday Cake -->
                            <div style="
                                font-size: 50px;
                                margin-bottom: 15px;
                                display: inline-block;
                            " class="bounce-animation">🎂</div>

                            <!-- Main Title -->
                            <h1 style="
                                color: #ffffff;
                                font-size: 36px;
                                font-weight: bold;
                                margin: 0 0 10px 0;
                                line-height: 1.2;
                            " class="mobile-text-large">
                                Happy Birthday, {{ $RECIPIENT_NAME }}! 🎉
                            </h1>

                            <p style="
                                color: rgba(255,255,255,0.9);
                                font-size: 18px;
                                margin: 0;
                                font-weight: 500;
                            " class="pulse-animation mobile-text-medium">
                                A day to celebrate you and all that you are.
                            </p>

                            <!-- Additional Decorative Elements -->
                            <div style="
                                position: absolute;
                                bottom: 15px;
                                left: 25px;
                                font-size: 25px;
                                opacity: 0.6;
                            " class="bounce-animation">🎁</div>

                            <div style="
                                position: absolute;
                                bottom: 15px;
                                right: 25px;
                                font-size: 20px;
                                opacity: 0.6;
                            " class="float-animation">✨</div>
                        </td>
                    </tr>

                    <!-- Main Content Section -->
                    <tr>
                        <td style="padding: 40px 30px;" class="mobile-padding">

                            <!-- Welcome Message -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 25px;">
                                        <p style="
                                            font-size: 18px;
                                            font-weight: 500;
                                            color: #1f2937;
                                            margin: 0;
                                            line-height: 1.6;
                                        " class="mobile-text-small">
                                            On behalf of the entire <span style="
                                                font-weight: 700;
                                                color: #114629;
                                            ">{{ $ORGANIZATION_NAME }}</span> team,
                                            we wish you joy, success, and great memories on your special day.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Role Message -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 25px;">
                                        <p style="
                                            font-size: 16px;
                                            color: #374151;
                                            margin: 0;
                                            line-height: 1.6;
                                        " class="mobile-text-small">
                                            Your presence as a valued <span style="
                                                font-style: italic;
                                                font-weight: 600;
                                                color: #114629;
                                            ">{{ $RECIPIENT_TYPE }}</span> has brought growth and inspiration to those
                                            around you.
                                            May this new year of life bring new heights and renewed purpose.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Birthday Snapshot Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="
                                        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #f0fdfa 100%);
                                        border-left: 4px solid #114629;
                                        border-radius: 12px;
                                        padding: 25px;
                                        margin-bottom: 25px;
                                        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                                    ">
                                        <h3 style="
                                            color: #114629;
                                            font-size: 18px;
                                            font-weight: 700;
                                            margin: 0 0 20px 0;
                                        ">
                                            🎁 Birthday Snapshot
                                        </h3>

                                        <!-- Snapshot Grid -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%">
                                            <tr>
                                                <!-- Name -->
                                                <td style="
                                                    width: 33.33%;
                                                    text-align: center;
                                                    padding: 15px 10px;
                                                    background-color: #ffffff;
                                                    border-radius: 8px;
                                                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                                                " class="mobile-block mobile-center">
                                                    <div style="font-size: 30px; margin-bottom: 5px;">👤</div>
                                                    <div style="
                                                        font-weight: 600;
                                                        color: #114629;
                                                        font-size: 14px;
                                                        margin-bottom: 2px;
                                                    ">{{ $RECIPIENT_NAME }}</div>
                                                    <div style="
                                                        font-size: 12px;
                                                        color: #6b7280;
                                                    ">Name</div>
                                                </td>

                                                <!-- Spacer for mobile -->
                                                <td style="width: 10px;" class="mobile-hide"></td>

                                                <!-- Birth Date -->
                                                <td style="
                                                    width: 33.33%;
                                                    text-align: center;
                                                    padding: 15px 10px;
                                                    background-color: #ffffff;
                                                    border-radius: 8px;
                                                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                                                " class="mobile-block mobile-center">
                                                    <div style="font-size: 30px; margin-bottom: 5px;">📅</div>
                                                    <div style="
                                                        font-weight: 600;
                                                        color: #114629;
                                                        font-size: 14px;
                                                        margin-bottom: 2px;
                                                    ">{{ $BIRTH_DATE }}</div>
                                                    <div style="
                                                        font-size: 12px;
                                                        color: #6b7280;
                                                    ">Birth Date</div>
                                                </td>

                                                <!-- Spacer for mobile -->
                                                <td style="width: 10px;" class="mobile-hide"></td>

                                                <!-- Role -->
                                                <td style="
                                                    width: 33.33%;
                                                    text-align: center;
                                                    padding: 15px 10px;
                                                    background-color: #ffffff;
                                                    border-radius: 8px;
                                                    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
                                                " class="mobile-block mobile-center">
                                                    <div style="font-size: 30px; margin-bottom: 5px;">🏆</div>
                                                    <div style="
                                                        font-weight: 600;
                                                        color: #114629;
                                                        font-size: 14px;
                                                        margin-bottom: 2px;
                                                    ">{{ $RECIPIENT_TYPE }}</div>
                                                    <div style="
                                                        font-size: 12px;
                                                        color: #6b7280;
                                                    ">Role</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Inspirational Quote -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="
                                        background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
                                        border-left: 4px solid #114629;
                                        border-radius: 0 12px 12px 0;
                                        padding: 20px;
                                        margin-bottom: 25px;
                                        position: relative;
                                    ">
                                        <p style="
                                            color: #374151;
                                            font-style: italic;
                                            font-size: 16px;
                                            margin: 0;
                                            line-height: 1.6;
                                        " class="mobile-text-small">
                                            "Your birthday is not just a celebration of your birth but a reminder of the
                                            positive energy and joy you bring to others. Shine on!"
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Birthday Wishes Section -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="
                                        background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
                                        border-radius: 12px;
                                        padding: 25px;
                                        border: 1px solid #fbbf24;
                                    ">
                                        <h3 style="
                                            color: #92400e;
                                            font-size: 18px;
                                            font-weight: 700;
                                            text-align: center;
                                            margin: 0 0 20px 0;
                                        ">
                                            🌟 Our Birthday Wishes for You 🌟
                                        </h3>

                                        <!-- Wishes Grid -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0"
                                            width="100%">
                                            <tr>
                                                <td style="width: 50%; padding: 8px;" class="mobile-block">
                                                    <div style="display: flex; align-items: center;">
                                                        <span style="font-size: 25px; margin-right: 10px;">🎯</span>
                                                        <span style="color: #374151; font-size: 14px;">Success in all
                                                            endeavors</span>
                                                    </div>
                                                </td>
                                                <td style="width: 50%; padding: 8px;" class="mobile-block">
                                                    <div style="display: flex; align-items: center;">
                                                        <span style="font-size: 25px; margin-right: 10px;">💫</span>
                                                        <span style="color: #374151; font-size: 14px;">Dreams coming
                                                            true</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="width: 50%; padding: 8px;" class="mobile-block">
                                                    <div style="display: flex; align-items: center;">
                                                        <span style="font-size: 25px; margin-right: 10px;">🌈</span>
                                                        <span style="color: #374151; font-size: 14px;">Joy and
                                                            happiness</span>
                                                    </div>
                                                </td>
                                                <td style="width: 50%; padding: 8px;" class="mobile-block">
                                                    <div style="display: flex; align-items: center;">
                                                        <span style="font-size: 25px; margin-right: 10px;">🚀</span>
                                                        <span style="color: #374151; font-size: 14px;">New adventures
                                                            ahead</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- Footer Section -->
                    <tr>
                        <td style="
                            background: linear-gradient(135deg, #114629 0%, #1d5a3f 50%, #2d7a5f 100%);
                            color: #ffffff;
                            text-align: center;
                            padding: 25px 30px;
                        ">
                            <div style="font-size: 40px; margin-bottom: 10px;">🎉🎊🎈</div>
                            <p style="
                                font-size: 16px;
                                font-weight: 500;
                                margin: 0 0 5px 0;
                            ">&copy; 2024 {{ $ORGANIZATION_NAME }}</p>
                            <p style="
                                font-size: 14px;
                                opacity: 0.9;
                                margin: 0;
                            ">Celebrating our people every step of the way.</p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

    <!-- Template Instructions (Remove in production) -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 20px;">
        <tr>
            <td align="center">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="
                    max-width: 600px;
                    background-color: #f8fafc;
                    border-radius: 10px;
                    padding: 20px;
                    border: 1px solid #e2e8f0;
                ">
                    <tr>
                        <td>
                            <h3 style="color: #1e40af; margin: 0 0 15px 0; font-size: 16px;">📝 Template Setup
                                Instructions</h3>
                            <div style="font-size: 14px; color: #475569; line-height: 1.5;">
                                <p style="margin: 0 0 10px 0;"><strong>Replace these variables:</strong></p>
                                <ul style="margin: 0; padding-left: 20px;">
                                    <li><code>{{ $RECIPIENT_NAME }}</code> - Person's full name</li>
                                    <li><code>{{ $BIRTH_DATE }}</code> - Birth date (e.g., March 15)</li>
                                    <li><code>{{ $RECIPIENT_TYPE }}</code> - Student or Staff</li>
                                    <li><code>{{ $ORGANIZATION_NAME }}</code> - Your institution name</li>
                                </ul>
                                <p style="margin: 10px 0 0 0;"><strong>Image:</strong> Replace the placeholder image URL
                                    with the actual celebrant's photo.</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <script>(function () { function c() { var b = a.contentDocument || a.contentWindow.document; if (b) { var d = b.createElement('script'); d.innerHTML = "window.__CF$cv$params={r:'969ec3a6e0984866',t:'MTc1NDMxNzk0OC4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);"; b.getElementsByTagName('head')[0].appendChild(d) } } if (document.body) { var a = document.createElement('iframe'); a.height = 1; a.width = 1; a.style.position = 'absolute'; a.style.top = 0; a.style.left = 0; a.style.border = 'none'; a.style.visibility = 'hidden'; document.body.appendChild(a); if ('loading' !== document.readyState) c(); else if (window.addEventListener) document.addEventListener('DOMContentLoaded', c); else { var e = document.onreadystatechange || function () { }; document.onreadystatechange = function (b) { e(b); 'loading' !== document.readyState && (document.onreadystatechange = e, c()) } } } })();</script>
</body>

</html>