<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Footer</title>
    <style>
        /* Footer Styling */
        .marketplace-footer {
            background-color: #0a0a23; /* Deep dark blue */
            color: #ffffff; /* White text */
            font-family: 'Poppins', sans-serif;
            padding: 40px 20px;
        }

        .marketplace-footer .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 30px;
            border-bottom: 1px solid #1a1a3b; /* Lighter dark blue for contrast */
            padding-bottom: 20px;
        }

        .footer-brand {
            flex: 1;
            min-width: 200px;
        }

        .footer-brand h3 {
            font-size: 1.6rem;
            font-weight: bold;
            color: #ffffff; /* White text */
            margin-bottom: 10px;
        }

        .footer-brand p {
            font-size: 1rem;
            color: #d1d1e0; /* Light grayish-blue text */
            line-height: 1.6;
        }

        .footer-links,
        .footer-legal {
            flex: 1;
            min-width: 180px;
        }

        .footer-links h4,
        .footer-legal h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: #ffffff; /* White text */
        }

        .footer-links ul,
        .footer-legal ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links ul li,
        .footer-legal ul li {
            margin-bottom: 8px;
        }

        .footer-links ul li a,
        .footer-legal ul li a {
            font-size: 1rem;
            color: #d1d1e0; /* Light grayish-blue text */
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-links ul li a:hover,
        .footer-legal ul li a:hover {
            color: #ffffff; /* White on hover */
        }

        .footer-contact {
            flex: 1;
            min-width: 200px;
        }

        .footer-contact h4 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 12px;
            color: #ffffff; /* White text */
        }

        .footer-contact p {
            font-size: 1rem;
            color: #d1d1e0; /* Light grayish-blue text */
            margin-bottom: 10px;
        }

        .footer-social a {
            font-size: 1.5rem;
            color: #d1d1e0; /* Light grayish-blue text */
            margin-right: 15px;
            transition: color 0.3s ease, transform 0.3s ease;
        }

        .footer-social a:hover {
            color: #ffffff; /* White on hover */
            transform: scale(1.2);
        }

        .footer-bottom {
            margin-top: 20px;
            text-align: center;
            font-size: 0.9rem;
            color: #d1d1e0; /* Light grayish-blue text */
        }

        .footer-bottom p {
            margin: 0;
        }

        @media (max-width: 768px) {
            .footer-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .footer-brand,
            .footer-links,
            .footer-legal,
            .footer-contact {
                flex: 0 0 auto;
            }

            .footer-social {
                margin-top: 20px;
            }
        }
    </style>
</head>
<body>
    <footer class="marketplace-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <h3>JABU Market</h3>
                    <p>Your trusted campus marketplace for all your buying and selling needs.</p>
                </div>
                <div class="footer-links">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="marketplace.php">Marketplace</a></li>
                        <li><a href="sell.php">Sell</a></li>
                        <li><a href="profile.php">My Profile</a></li>
                    </ul>
                </div>
                <div class="footer-legal">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="privacy-policy.php">Privacy Policy</a></li>
                        <li><a href="terms-of-service.php">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h4>Contact Us</h4>
                    <p>Email: support@jabumarket.com</p>
                    <p>Phone: +1 234 567 8900</p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f" aria-hidden="true"></i></a>
                        <a href="#"><i class="fab fa-twitter" aria-hidden="true"></i></a>
                        <a href="#"><i class="fab fa-instagram" aria-hidden="true"></i></a>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 JABU Market. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
