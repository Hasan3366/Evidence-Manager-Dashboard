<?php
/**
 * auth/login.php
 * User login page.
 *
 * Accessible to guests only.
 */

$pageTitle = 'Sign In';
$bodyClass = 'auth-page';

require_once __DIR__ . '/../includes/header.php';

// If already authenticated, do not allow access to login page.
if (isLoggedIn()) {
    redirect('/dashboard/index.php');
}

// Create a CSRF token for this login form.
$csrfToken = generateCsrfToken();
?>

<style>
    body.auth-page {
        min-height: 100vh;
        background: linear-gradient(145deg, #0a1f44 0%, #123d7a 55%, #1d4f91 100%);
    }

    .auth-page .site-footer {
        display: none;
    }

    .auth-page .main-content {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .login-shell {
        width: 100%;
        max-width: 420px;
    }

    .login-card {
        background: #ffffff;
        border-radius: 18px;
        box-shadow: 0 24px 60px rgba(6, 15, 36, 0.35);
        overflow: hidden;
        padding: 2rem 1.6rem 1.5rem;
    }

    .login-icon-wrap {
        display: flex;
        justify-content: center;
        margin-top: 0.25rem;
        margin-bottom: 1rem;
    }

    .login-icon {
        width: 62px;
        height: 62px;
        border-radius: 50%;
        background: #dc2626;
        color: #ffffff;
        display: grid;
        place-items: center;
        font-size: 1.5rem;
        font-weight: 700;
        box-shadow: 0 8px 18px rgba(220, 38, 38, 0.35);
    }

    .login-title {
        text-align: center;
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .login-subtitle {
        text-align: center;
        margin: 0.4rem 0 1.5rem;
        color: #64748b;
        font-size: 0.92rem;
    }

    .login-form .form-group {
        margin-bottom: 1rem;
    }

    .login-form .form-label {
        font-size: 0.86rem;
        margin-bottom: 0.35rem;
        color: #334155;
        font-weight: 600;
    }

    .login-form .form-control {
        border-radius: 10px;
        border: 1px solid #cbd5e1;
        padding: 0.7rem 0.85rem;
        font-size: 0.95rem;
    }

    .login-form .form-control:focus {
        border-color: #1d4f91;
        box-shadow: 0 0 0 3px rgba(29, 79, 145, 0.15);
    }

    .login-submit {
        width: 100%;
        border: 0;
        border-radius: 10px;
        background: #dc2626;
        color: #ffffff;
        font-weight: 700;
        padding: 0.75rem 1rem;
        margin-top: 0.5rem;
        transition: background 0.2s ease;
    }

    .login-submit:hover {
        background: #b91c1c;
    }

    .login-helper {
        margin: 1rem 0 0;
        text-align: center;
        font-size: 0.78rem;
        color: #64748b;
    }

    @media (max-width: 480px) {
        .login-card {
            padding: 1.5rem 1rem 1.25rem;
        }

        .login-title {
            font-size: 1.3rem;
        }

        .login-subtitle {
            font-size: 0.85rem;
        }
    }
</style>

<div class="login-shell">
    <section class="login-card" aria-labelledby="login-title">
        <div class="login-icon-wrap" aria-hidden="true">
            <div class="login-icon">!</div>
        </div>

        <h1 id="login-title" class="login-title">Evidence Manager</h1>
        <p class="login-subtitle">First Responder Digital Evidence System</p>

        <form class="login-form" action="<?= BASE_URL ?>/auth/process_login.php" method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input
                    class="form-control"
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                    maxlength="50"
                    autofocus
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input
                    class="form-control"
                    type="password"
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit" class="login-submit">Sign In</button>
        </form>

        <p class="login-helper">Authorized personnel only. All login activity is monitored.</p>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
