<?php

declare(strict_types=1);

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Flash ERP — Coming Soon</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #e2e8f0;
    font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
}
.card {
    background: rgba(255,255,255,0.04);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.08);
    border-radius: 24px;
    padding: 4rem 3rem;
    max-width: 520px;
    text-align: center;
}
.logo {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    border-radius: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
}
h1 {
    font-size: 1.75rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #f1f5f9;
}
p {
    color: #94a3b8;
    margin-bottom: 0;
    line-height: 1.6;
}
.pulse {
    display: inline-flex;
    gap: 6px;
    margin-top: 2rem;
    align-items: center;
    font-size: 0.85rem;
    color: #64748b;
}
.pulse span {
    width: 8px;
    height: 8px;
    background: #22c55e;
    border-radius: 50%;
    animation: blink 1.4s infinite both;
}
.pulse span:nth-child(2) { animation-delay: 0.2s; }
.pulse span:nth-child(3) { animation-delay: 0.4s; }
@keyframes blink {
    0%, 80%, 100% { opacity: 0.3; transform: scale(0.8); }
    40% { opacity: 1; transform: scale(1); }
}
</style>
</head>
<body>
<div class="card">
    <div class="logo">FE</div>
    <h1>We're launching soon</h1>
    <p>Flash ERP is under development. The dashboard is available for authorized users at the direct URL.</p>
    <div class="pulse">
        <span></span><span></span><span></span>
        System is live
    </div>
</div>
</body>
</html>
