<?php

class Security {
    
    /**
     * Validar formato de entrada de login
     */
    public static function validateLoginInput($input) {
        // Limpiar entrada
        $input = trim($input);
        
        // Verificar longitud mínima
        if (strlen($input) < 3) {
            return false;
        }
        
        // Verificar longitud máxima
        if (strlen($input) > 100) {
            return false;
        }
        
        // Verificar caracteres permitidos (letras, números, @, ., _, -)
        if (!preg_match('/^[a-zA-Z0-9@._-]+$/', $input)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Detectar intentos de SQL Injection
     */
    public static function detectSQLInjection($input) {
        $suspicious_patterns = [
            '/union\s+select/i',
            '/drop\s+table/i',
            '/delete\s+from/i',
            '/insert\s+into/i',
            '/update\s+set/i',
            '/or\s+1\s*=\s*1/i',
            '/and\s+1\s*=\s*1/i',
            '/\'\s*or\s*\'/i',
            '/\'\s*and\s*\'/i',
            '/\s*;\s*/',
            '/\s*--\s*/',
            '/\s*\/\*\s*/',
            '/\s*\*\/\s*/'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rate limiting para intentos de login
     */
    public static function checkRateLimit($ip, $max_attempts = 5, $time_window = 300) {
        $file = __DIR__ . '/../../logs/login_attempts.json';
        
        // Crear directorio si no existe
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        
        $attempts = [];
        if (file_exists($file)) {
            $attempts = json_decode(file_get_contents($file), true) ?? [];
        }
        
        // Limpiar intentos antiguos
        $current_time = time();
        $attempts = array_filter($attempts, function($attempt) use ($current_time, $time_window) {
            return ($current_time - $attempt['timestamp']) < $time_window;
        });
        
        // Contar intentos de esta IP
        $ip_attempts = array_filter($attempts, function($attempt) use ($ip) {
            return $attempt['ip'] === $ip;
        });
        
        if (count($ip_attempts) >= $max_attempts) {
            return false; // Rate limit excedido
        }
        
        // Registrar nuevo intento
        $attempts[] = [
            'ip' => $ip,
            'timestamp' => $current_time
        ];
        
        file_put_contents($file, json_encode($attempts));
        return true;
    }
    
    /**
     * Log de intentos de login sospechosos
     */
    public static function logSuspiciousActivity($ip, $input, $reason) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'input' => $input,
            'reason' => $reason
        ];
        
        $log_file = __DIR__ . '/../../logs/security.log';
        
        // Crear directorio si no existe
        if (!file_exists(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        file_put_contents($log_file, json_encode($log_entry) . "\n", FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Validar si el input es un email válido
     */
    public static function isValidEmail($input) {
        return filter_var($input, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar si el input es un username válido
     */
    public static function isValidUsername($input) {
        // Username: 3-20 caracteres, solo letras, números y guiones bajos
        return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $input);
    }
}

