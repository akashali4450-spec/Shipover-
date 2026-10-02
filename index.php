<?php
// index.php
$siteName = "MyWeb";
$year = date("Y");

$features = [
    [
        "icon" => "🚀",
        "title" => "Fast & Simple",
        "text" => "A clean and responsive experience designed for every device."
    ],
    [
        "icon" => "🔒",
        "title" => "Secure",
        "text" => "Built with a simple structure that you can customize safely."
    ],
    [
        "icon" => "📱",
        "title" => "Mobile Friendly",
        "text" => "Looks great on phones, tablets and desktop screens."
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($siteName) ?> - Home</title>

    <link rel="stylesheet" href="style.css">
</head>

<body>

<header class="header">
    <div class="container nav">

        <a href="index.php" class="logo">
            <?= htmlspecialchars($siteName) ?>
        </a>

        <nav class="menu">
            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>
        </nav>

        <div class="nav-buttons">
            <a href="#" class="btn btn-outline">Login</a>
            <a href="#" class="btn btn-primary">Register</a>
        </div>

    </div>
</header>


<main>

<!-- HERO -->
<section class="hero" id="home">
    <div class="container hero-content">

        <div class="hero-text">
            <span class="badge">Welcome to <?= htmlspecialchars($siteName) ?></span>

            <h1>
                Build your
                <span>digital experience</span>
                with ease.
            </h1>

            <p>
                A modern, clean and responsive homepage template
                built with PHP, HTML and CSS.
            </p>

            <div class="hero-buttons">
                <a href="#" class="btn btn-primary btn-large">
                    Get Started
                </a>

                <a href="#features" class="btn btn-light btn-large">
                    Explore Features
                </a>
            </div>
        </div>

        <div class="hero-card">

            <div class="card-top">
                <span class="dot"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>

            <div class="dashboard">
                <div class="dashboard-icon">✨</div>

                <h3>Welcome!</h3>

                <p>
                    Your modern web experience starts here.
                </p>

                <div class="progress">
                    <div></div>
                </div>

                <div class="stats">
                    <div>
                        <strong>100%</strong>
                        <small>Responsive</small>
                    </div>

                    <div>
                        <strong>24/7</strong>
                        <small>Available</small>
                    </div>
                </div>
            </div>

        </div>

    </div>
</section>


<!-- FEATURES -->
<section class="features" id="features">

    <div class="container">

        <div class="section-heading">
            <span class="section-label">FEATURES</span>

            <h2>Everything you need</h2>

            <p>
                Simple components that can be customized for your own project.
            </p>
        </div>


        <div class="feature-grid">

            <?php foreach ($features as $feature): ?>

                <div class="feature-card">

                    <div class="feature-icon">
                        <?= $feature["icon"] ?>
                    </div>

                    <h3>
                        <?= htmlspecialchars($feature["title"]) ?>
                    </h3>

                    <p>
                        <?= htmlspecialchars($feature["text"]) ?>
                    </p>

                    <a href="#contact">
                        Learn more →
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>


<!-- ABOUT -->
<section class="about" id="about">

    <div class="container about-grid">

        <div class="about-box">
            <div class="about-number">01</div>

            <h3>Simple Design</h3>

            <p>
                Keep your interface clean and easy to understand.
            </p>
        </div>


        <div class="about-content">

            <span class="section-label">ABOUT US</span>

            <h2>
                A flexible starting point for your website.
            </h2>

            <p>
                This template uses PHP for dynamic content and CSS
                for the visual design. You can connect it to MySQL,
                authentication, APIs or your own backend later.
            </p>

            <a href="#contact" class="btn btn-primary">
                Contact Us
            </a>

        </div>

    </div>

</section>


<!-- CTA -->
<section class="cta">

    <div class="container">

        <h2>Ready to get started?</h2>

        <p>
            Customize this page and make it your own.
        </p>

        <a href="#" class="btn btn-white">
            Create Account
        </a>

    </div>

</section>


<!-- CONTACT -->
<section class="contact" id="contact">

    <div class="container">

        <div class="section-heading">

            <span class="section-label">CONTACT</span>

            <h2>Get in touch</h2>

            <p>
                Have a question? Send us a message.
            </p>

        </div>


        <form class="contact-form" method="post" action="#">

            <div class="form-row">

                <div class="form-group">
                    <label for="name">Name</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        placeholder="Your name"
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        placeholder="you@example.com"
                    >
                </div>

            </div>


            <div class="form-group">

                <label for="message">Message</label>

                <textarea
                    id="message"
                    name="message"
                    rows="6"
                    placeholder="Write your message..."
                ></textarea>

            </div>


            <button type="submit" class="btn btn-primary">
                Send Message
            </button>

        </form>

    </div>

</section>

</main>


<!-- FOOTER -->
<footer class="footer">

    <div class="container footer-content">

        <div>
            <a href="index.php" class="logo">
                <?= htmlspecialchars($siteName) ?>
            </a>

            <p>
                A modern PHP website template.
            </p>
        </div>


        <div class="footer-links">

            <a href="#home">Home</a>
            <a href="#features">Features</a>
            <a href="#about">About</a>
            <a href="#contact">Contact</a>

        </div>

    </div>


    <div class="copyright">
        © <?= $year ?> <?= htmlspecialchars($siteName) ?>.
        All rights reserved.
    </div>

</footer>

</body>
</html>
