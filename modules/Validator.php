<?php
class Validator {

    public static function sanitize(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateEmail(string $email): bool {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    public static function validateRegistration(array $data, PDO $db): array {
        $errors = [];
        $required = ['username', 'email', 'password', 'phone'];
        foreach ($required as $field) {
            if (empty(trim($data[$field] ?? ''))) {
                $errors[$field] = 'This field is required.';
            }
        }
        if (!empty($data['username'])) {
            $len = strlen(trim($data['username']));
            if ($len < 1 || $len > 50) {
                $errors['username'] = 'Username must be 1–50 characters.';
            } else {
                $stmt = $db->prepare('SELECT id FROM clients WHERE username = ?');
                $stmt->execute([trim($data['username'])]);
                if ($stmt->fetch()) $errors['username'] = 'This username is already taken.';
            }
        }
        if (!empty($data['email'])) {
            if (!self::validateEmail($data['email'])) {
                $errors['email'] = 'Please enter a valid email address.';
            } else {
                $stmt = $db->prepare('SELECT id FROM clients WHERE email = ?');
                $stmt->execute([trim($data['email'])]);
                if ($stmt->fetch()) $errors['email'] = 'This email address is already registered.';
            }
        }
        if (!empty($data['password'])) {
            $len = strlen($data['password']);
            if ($len < 8) $errors['password'] = 'Password must be at least 8 characters.';
            if ($len > 128) $errors['password'] = 'Password must not exceed 128 characters.';
        }
        return $errors;
    }

    public static function validateLogin(array $data): array {
        $errors = [];
        if (empty(trim($data['email'] ?? ''))) {
            $errors['email'] = 'Email is required.';
        } elseif (!self::validateEmail($data['email'])) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if (empty($data['password'] ?? '')) {
            $errors['password'] = 'Password is required.';
        }
        return $errors;
    }

    public static function validateFile(array $file, array $allowedMimes, int $maxBytes): array {
        $errors = [];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload failed. Please try again.';
            return $errors;
        }
        if ($file['size'] > $maxBytes) {
            $errors[] = 'File size must not exceed ' . round($maxBytes / 1048576) . ' MB.';
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowedMimes, true)) {
            $errors[] = 'Please upload a JPEG, PNG, or GIF image only.';
        }
        return $errors;
    }

    public static function validatePassword(string $password): array {
        $errors = [];
        if (strlen($password) < 8)   $errors[] = 'Password must be at least 8 characters.';
        if (strlen($password) > 128) $errors[] = 'Password must not exceed 128 characters.';
        return $errors;
    }
}
