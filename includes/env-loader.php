<?php
/**
 * Environment File Loader
 * 
 * CRA-inspired .env loading with priority hierarchy.
 * 
 * Priority order (highest → lowest):
 *   Development: .env.development.local → .env.local → .env.development → .env
 *   Production:  .env.production.local  → .env.local → .env.production  → .env
 * 
 * APP_ENV detection:
 *   1. Server/system environment variable APP_ENV
 *   2. Defaults to 'development'
 * 
 * Files ending in .local are gitignored (secrets go there).
 * .env.development and .env.production are committed (non-secret defaults).
 * 
 * @package GWN-Portal
 * @since M2
 */

/**
 * Parse a single .env file and return key-value pairs.
 *
 * Supports comments (#), quoted values, and ${VAR} interpolation
 * against previously parsed variables.
 *
 * @param string $filePath Absolute path to the .env file
 * @param array  $existing Already-loaded variables for interpolation
 * @return array Parsed key-value pairs
 */
function parseEnvFile(string $filePath, array $existing = []): array
{
    $vars = [];

    if (!is_file($filePath) || !is_readable($filePath)) {
        return $vars;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and lines without =
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name  = trim($name);
        $value = trim($value);

        // Remove surrounding quotes
        if (preg_match('/^"(.+)"$/', $value, $m)) {
            $value = $m[1];
        } elseif (preg_match("/^'(.+)'$/", $value, $m)) {
            $value = $m[1];
        }

        // Interpolate ${VAR} references against already-loaded vars
        $merged = array_merge($existing, $vars);
        $value  = preg_replace_callback('/\${([a-zA-Z0-9_]+)}/', function ($match) use ($merged) {
            return $merged[$match[1]] ?? '';
        }, $value);

        $vars[$name] = $value;
    }

    return $vars;
}

/**
 * Load environment files following CRA-style priority.
 *
 * Higher-priority files override lower-priority ones, but system-level
 * environment variables (set by Docker, Apache, the OS, etc.) always win.
 * This matches CRA / dotenv behaviour where .env files act as defaults.
 *
 * @param string|null $rootDir  Project root (defaults to one level above __DIR__)
 * @param string|null $appEnv   Force a specific environment (skips auto-detection)
 * @return array Final merged environment variables
 */
function loadEnvironment(?string $rootDir = null, ?string $appEnv = null): array
{
    $rootDir = $rootDir ?? realpath(__DIR__ . '/..');

    // ----------------------------------------------------------------
    // 1. Detect APP_ENV
    //    Priority: explicit param → system env var → .env file → default
    //    The .env pre-scan mirrors Symfony's loadEnv() behaviour so that
    //    production servers without Docker can simply put APP_ENV=production
    //    in a .env file and everything cascades correctly.
    // ----------------------------------------------------------------
    if ($appEnv === null) {
        // Check system environment first (Docker, Apache SetEnv, OS export)
        $appEnv = getenv('APP_ENV') ?: null;

        if ($appEnv === null && isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] !== '') {
            $appEnv = $_SERVER['APP_ENV'];
        }

        // Still unknown? Pre-scan .env for an APP_ENV line
        if ($appEnv === null) {
            $dotenvPath = $rootDir . DIRECTORY_SEPARATOR . '.env';
            if (is_file($dotenvPath) && is_readable($dotenvPath)) {
                $bootstrap = parseEnvFile($dotenvPath);
                if (isset($bootstrap['APP_ENV']) && $bootstrap['APP_ENV'] !== '') {
                    $appEnv = $bootstrap['APP_ENV'];
                }
            }
        }

        // Ultimate fallback
        $appEnv = $appEnv ?? 'development';
    }

    // Normalise to lowercase
    $appEnv = strtolower($appEnv);

    // Build file list in REVERSE priority (lowest first, highest last)
    // so that later files overwrite earlier ones via array_merge.
    $files = [
        '.env',                          // 4. shared defaults
        ".env.{$appEnv}",                // 3. environment-specific
        '.env.local',                    // 2. local overrides (all envs)
        ".env.{$appEnv}.local",          // 1. environment-specific local overrides
    ];

    // Merge all files (later = higher priority)
    $merged = [];
    foreach ($files as $file) {
        $path   = $rootDir . DIRECTORY_SEPARATOR . $file;
        $parsed = parseEnvFile($path, $merged);
        $merged = array_merge($merged, $parsed);
    }

    // Inject APP_ENV itself so the rest of the app can read it
    $merged['APP_ENV'] = $appEnv;

    // Push into PHP superglobals, but NEVER override a variable that is
    // already set in the real system environment (Docker, Apache, OS, etc.).
    foreach ($merged as $key => $value) {
        $systemValue = getenv($key);
        if ($systemValue !== false) {
            // System already has this var — keep the system value and
            // update $merged so callers see the authoritative value.
            $merged[$key] = $systemValue;
        } else {
            putenv("{$key}={$value}");
        }
        // $_ENV and $_SERVER: only set if not already present
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $merged[$key];
        } else {
            $merged[$key] = $_ENV[$key];
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $merged[$key];
        }
    }

    return $merged;
}
