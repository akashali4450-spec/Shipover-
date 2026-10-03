import zipfile, os

path="/mnt/data/SHIPOVR_home_only"
os.makedirs(path, exist_ok=True)

code = r'''<?php
// SHIPOVR - Home Page
// This file is the FIRST/HOME page only.
// The buttons are real links so they can continue to the next pages.

$registerPage = 'register.php';
$loginPage    = 'login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SHIPOVR</title>

<style>
*{
    box-sizing:border-box;
    margin:0;
    padding:0;
}

body{
    font-family:Arial,Helvetica,sans-serif;
    background:#f5f8ff;
    color:#172033;
}

/* Top navigation */
.navbar{
    height:68px;
    background:#ffffff;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 7%;
    box-shadow:0 1px 8px rgba(0,0,0,.08);
}

.logo{
    color:#155eef;
    font-size:23px;
    font-weight:800;
    text-decoration:none;
}

.nav-links a{
    text-decoration:none;
    color:#172033;
    margin-left:25px;
    font-size:14px;
}

/* First page / hero */
.hero{
    min-height:430px;
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    color:white;
    padding:60px 20px;

    /* Replace hero.jpg with your own image if you want */
    background:
        linear-gradient(rgba(10,65,165,.78),rgba(25,105,235,.78)),
        url('hero.jpg') center/cover no-repeat,
        linear-gradient(135deg,#0f4cbd,#2678ff);
}

.hero-content{
    max-width:800px;
}

.hero h1{
    font-size:55px;
    margin-bottom:15px;
    letter-spacing:.5px;
}

.hero p{
    font-size:19px;
    line-height:1.6;
    margin-bottom:24px;
}

.btn{
    display:inline-block;
    padding:14px 28px;
    border-radius:10px;
    text-decoration:none;
    font-weight:700;
    margin:6px;
    transition:.2s;
}

.btn:hover{
    transform:translateY(-2px);
}

.btn-register{
    background:#155eef;
    color:white;
    border:2px solid #155eef;
}

.btn-login{
    background:white;
    color:#155eef;
    border:2px solid white;
}

/* Feature cards */
.features{
    max-width:1050px;
    margin:-40px auto 55px;
    padding:0 20px;
    display:flex;
    gap:20px;
    position:relative;
}

.feature{
    flex:1;
    background:white;
    border-radius:16px;
    padding:28px 20px;
    text-align:center;
    box-shadow:0 8px 30px rgba(0,0,0,.10);
}

.icon{
    font-size:32px;
    margin-bottom:10px;
}

.feature h3{
    margin-bottom:8px;
}

.feature p{
    color:#667085;
    font-size:14px;
    line-height:1.5;
}

@media(max-width:700px){
    .navbar{
        padding:0 18px;
    }

    .nav-links a{
        margin-left:10px;
    }

    .hero{
        min-height:420px;
    }

    .hero h1{
        font-size:40px;
    }

    .hero p{
        font-size:16px;
    }

    .features{
        flex-direction:column;
        margin-top:25px;
    }
}
</style>
</head>

<body>

<!-- Header -->
<header class="navbar">
    <a class="logo" href="index.php">SHIPOVR</a>

    <nav class="nav-links">
        <a href="<?php echo htmlspecialchars($loginPage); ?>">Login</a>
        <a href="<?php echo htmlspecialchars($registerPage); ?>">Register</a>
    </nav>
</header>

<!-- FIRST PAGE -->
<section class="hero">
    <div class="hero-content">
        <h1>Welcome to SHIPOVR</h1>

        <p>
            Create an account or sign in to access your dashboard
            and enjoy our services.
        </p>

        <!-- These buttons actually go to the next pages -->
        <a class="btn btn-register"
           href="<?php echo htmlspecialchars($registerPage); ?>">
           Register
        </a>

        <a class="btn btn-login"
           href="<?php echo htmlspecialchars($loginPage); ?>">
           Login
        </a>
    </div>
</section>

<!-- Feature section shown on the FIRST page -->
<section class="features">

    <div class="feature">
        <div class="icon">👤</div>
        <h3>Easy Registration</h3>
        <p>Create your account in seconds.</p>
    </div>

    <div class="feature">
        <div class="icon">🛡️</div>
        <h3>Secure &amp; Safe</h3>
        <p>Your account information is protected.</p>
    </div>

    <div class="feature">
        <div class="icon">⚡</div>
        <h3>Fast Access</h3>
        <p>Get started quickly and easily.</p>
    </div>

</section>

</body>
</html>
'''

with open(path+"/index.php","w",encoding="utf-8") as f:
    f.write(code)

zip_path="/mnt/data/SHIPOVR_first_page_only.zip"
with zipfile.ZipFile(zip_path,"w",zipfile.ZIP_DEFLATED) as z:
    z.write(path+"/index.php","index.php")

zip_path
