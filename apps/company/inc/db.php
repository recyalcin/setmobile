<?php
// .env dosyasından DATABASE_URL oku
$envFile = __DIR__ . '/../../../.env';
if (file_exists($envFile)) {
    foreach (file($envFile) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (preg_match('/^DATABASE_URL\s*=\s*"?([^"#\s]+)"?/', $line, $m)) {
            putenv("DATABASE_URL=" . $m[1]);
            break;
        }
    }
}

$dsn = getenv('DATABASE_URL') ?: 'postgresql://setmobile:setmobile2025@localhost:5432/setmobile';

// postgresql://user:pass@host:port/dbname?schema=public  →  PDO pgsql:...
if (preg_match('#^postgresql://([^:]+):([^@]+)@([^:/]+):?(\d*)/([^?]+)#', $dsn, $m)) {
    [, $user, $pass, $host, $port, $dbname] = $m;
    $port  = $port ?: '5432';
    $pdoDsn = "pgsql:host=$host;port=$port;dbname=$dbname;";
} else {
    die("Geçersiz DATABASE_URL formatı.");
}

try {
    $pdo = new PDO($pdoDsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    // PostgreSQL: MySQL IF() fonksiyonu yok → PHP katmanında CASE WHEN kullanılır
    // NOT: PostgreSQL NOW(), CONCAT(), DATE_FORMAT'ı (to_char ile) doğal destekler.
    // Bazı MySQL-spesifik fonksiyonlar için uyumluluk notu:
    //   MySQL  IF(x,a,b)         → PostgreSQL  CASE WHEN x THEN a ELSE b END
    //   MySQL  IFNULL(a,b)       → PostgreSQL  COALESCE(a,b)  ✓ (desteklenir)
    //   MySQL  DATE_FORMAT(d,'%Y-%m-%d') → PostgreSQL  to_char(d,'YYYY-MM-DD')
    //   MySQL  GROUP_CONCAT(x)   → PostgreSQL  STRING_AGG(x,',')
    //   MySQL  DATEDIFF(a,b)     → PostgreSQL  (a::date - b::date)
} catch (PDOException $e) {
    die("DB Bağlantı Hatası: " . $e->getMessage());
}
?>