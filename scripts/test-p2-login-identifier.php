<?php
declare(strict_types=1);

use App\Libraries\Auth\LoginIdentifier;

require dirname(__DIR__) . '/app/Libraries/Auth/LoginIdentifier.php';

$cases = [
    ['08000000000', 'phone', '+2348000000000'],
    ['+2348000000000', 'phone', '+2348000000000'],
    ['2348000000000', 'phone', '+2348000000000'],
    ['Test.Person@Example.COM', 'email', 'test.person@example.com'],
    ['+447911123456', 'phone', '+447911123456'],
];
foreach ($cases as [$input, $type, $expected]) {
    $got = LoginIdentifier::normalise($input);
    if ($got !== ['type' => $type, 'value' => $expected]) {
        fwrite(STDERR, "FAILED canonicalisation test\n");
        exit(1);
    }
}
foreach (['', 'not-an-email', '0800000000', '+2340000000000', 'bad@example', '08000000000<script>'] as $invalid) {
    try {
        LoginIdentifier::normalise($invalid);
        fwrite(STDERR, "FAILED invalid identifier rejection test\n");
        exit(1);
    } catch (InvalidArgumentException) {
        // Expected.
    }
}
fwrite(STDOUT, "P2 identifier normalisation smoke tests passed.\n");
