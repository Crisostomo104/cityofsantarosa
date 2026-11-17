<?php
// 404.php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>404 Not Found</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" href="images/ibaba2.png" type="image/x-icon">
  <style>
    body {
      font-family: "Segoe UI", Arial, sans-serif;
      background: #f9fafb;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      color: #374151;
    }
    .error-container {
      text-align: center;
      background: #fff;
      padding: 40px;
      border-radius: 12px;
      box-shadow: 0 6px 20px rgba(0,0,0,0.08);
      max-width: 500px;
      width: 90%;
    }
    .error-code {
      font-size: 5rem;
      font-weight: 800;
      color: #C62828;
      margin: 0;
    }
    .error-message {
      font-size: 1.5rem;
      margin: 10px 0 20px;
      color: #111827;
    }
    p {
      font-size: 1rem;
      line-height: 1.6;
      margin-bottom: 20px;
    }
    a {
      display: inline-block;
      padding: 10px 20px;
      background: linear-gradient(135deg, #8E1B1B, #C62828);
      color: #fff;
      text-decoration: none;
      border-radius: 6px;
      font-weight: 600;
      transition: background 0.3s ease;
    }
    a:hover {
      background: linear-gradient(135deg, #C62828, #8E1B1B);
    }
    @media (max-width: 480px) {
      .error-code {
        font-size: 3.5rem;
      }
      .error-message {
        font-size: 1.2rem;
      }
      p {
        font-size: 0.9rem;
      }
    }
  </style>
</head>
<body>
  <div class="error-container">
    <h1 class="error-code">404</h1>
    <h2 class="error-message">Page Not Found</h2>
    <p>Sorry, the page you’re looking for doesn’t exist or may have been moved.</p>
    <a href="/cityofsantarosa/auth/">Go Back</a>
  </div>
</body>
</html>
