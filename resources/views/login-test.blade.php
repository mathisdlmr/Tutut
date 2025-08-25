<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Connexion test</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    min-height: 100vh;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}

h1 {
    color: #ffffff;
    font-size: 2.5rem;
    font-weight: 300;
    text-align: center;
    margin-bottom: 3rem;
    position: relative;
}

h1::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 80px;
    height: 2px;
    background: linear-gradient(90deg, transparent, #ffffff, transparent);
}

.container {
    max-width: 600px;
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.user-card {
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
}

.user-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #000000, #333333, #000000);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.user-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.user-card:hover::before {
    opacity: 1;
}

.user-card form {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
}

.user-info {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.user-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 0.25rem;
}

.user-role {
    font-size: 0.9rem;
    color: #666666;
    background: #f5f5f5;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    display: inline-block;
    width: fit-content;
    border: 1px solid #e0e0e0;
}

.connect-btn {
    background: #000000;
    color: #ffffff;
    border: none;
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.connect-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s ease;
}

.connect-btn:hover {
    background: #333333;
    transform: translateX(2px);
}

.connect-btn:hover::before {
    left: 100%;
}

.connect-btn:active {
    transform: translateX(2px) scale(0.98);
}

/* Animation d'apparition */
.user-card {
    animation: fadeInUp 0.6s ease forwards;
    opacity: 0;
    transform: translateY(20px);
}

.user-card:nth-child(1) { animation-delay: 0.1s; }
.user-card:nth-child(2) { animation-delay: 0.2s; }
.user-card:nth-child(3) { animation-delay: 0.3s; }
.user-card:nth-child(4) { animation-delay: 0.4s; }
.user-card:nth-child(5) { animation-delay: 0.5s; }

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    body {
        padding: 1rem;
    }
    
    h1 {
        font-size: 2rem;
        margin-bottom: 2rem;
    }
    
    .user-card form {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }
    
    .connect-btn {
        width: 100%;
    }
}
</style>
</head>
<body>
    <h1>Connexion de test</h1>
    
    <div class="container">
        @foreach ($users as $user)
        <div class="user-card">
            <form method="POST" action="{{ url('/login-test') }}">
                @csrf
                <div class="user-info">
                    <span class="user-name">{{ $user->firstName }} {{ $user->lastName }}</span>
                    <span class="user-role">{{ \App\Enums\Roles::from($user->role)->label() }}</span>
                </div>
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <button type="submit" class="connect-btn">Se connecter</button>
            </form>
        </div>
        @endforeach
    </div>
</body>
</html>