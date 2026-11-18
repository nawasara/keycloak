<?php

/**
 * KeycloakUser::computeContentHash(): a change the hourly sync must store is
 * a change of the hash. Regression for 18 Nov 2026: attributes (simas_*)
 * were not hashed, so the sync never saved them for existing users.
 *
 *   php packages/nawasara-keycloak/tests/manual/uji-content-hash.php
 *   (through remote-test, never on the laptop)
 */

$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Nawasara\Keycloak\Models\KeycloakUser;

$gagal = 0;
$cek = function (string $label, bool $ok) use (&$gagal) {
    echo ($ok ? '  OK    ' : '  GAGAL ').$label."\n";
    if (! $ok) {
        $gagal++;
    }
};

$base = [
    'username' => '190001012020011001', 'email' => 'budi.contoh@example.com',
    'enabled' => true, 'email_verified' => true,
    'first_name' => 'BUDI', 'last_name' => 'CONTOH',
    'attributes' => ['nip' => ['190001012020011001']],
];
$hash = fn (array $over) => (new KeycloakUser(array_merge($base, $over)))->computeContentHash();
$h0 = $hash([]);

$cek('hash stabil untuk data yang sama', $h0 === $hash([]));
$cek('atribut simas_skpd baru mengubah hash', $h0 !== $hash(['attributes' => ['nip' => ['190001012020011001'], 'simas_skpd' => ['DINAS KOMUNIKASI DAN INFORMATIKA']]]));
$cek('nilai atribut berubah mengubah hash', $hash(['attributes' => ['simas_skpd' => ['A']]]) !== $hash(['attributes' => ['simas_skpd' => ['B']]]));
$cek('urutan kunci atribut tidak mengubah hash', $hash(['attributes' => ['a' => ['1'], 'b' => ['2']]]) === $hash(['attributes' => ['b' => ['2'], 'a' => ['1']]]));
$cek('nama berubah mengubah hash', $h0 !== $hash(['last_name' => 'CONTOH SH']));
$cek('email berubah tetap mengubah hash', $h0 !== $hash(['email' => 'lain@example.com']));
$cek('tanpa atribut tidak galat', is_string($hash(['attributes' => null])));

echo "\n".($gagal === 0 ? 'SEMUA LULUS' : "ADA {$gagal} YANG GAGAL")."\n";
exit($gagal === 0 ? 0 : 1);
