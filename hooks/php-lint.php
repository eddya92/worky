<?php

declare(strict_types=1);

/**
 * PostToolUse: dopo ogni Write/Edit su un file PHP ne verifica la sintassi.
 * Nessun autoload: questo script gira anche in un plugin installato senza vendor/.
 */

$raw = stream_get_contents(STDIN);
$input = json_decode((string) $raw, true);

if (!is_array($input)) {
    exit(0);
}

$path = $input['tool_input']['file_path'] ?? '';

// Estensione senza distinzione di maiuscole: Foo.PHP è PHP quanto foo.php.
if (!is_string($path) || preg_match('/\.(php|phtml|php\d)$/i', $path) !== 1 || !is_file($path)) {
    exit(0);
}

// PHP_BINARY, non un "php" qualunque nel PATH: il lint deve girare con lo
// stesso interprete che esegue l'hook.
exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($path)), $output, $code);

if ($code !== 0) {
    fwrite(STDERR, sprintf(
        "worky: errore di sintassi PHP in %s\n%s\nCorreggi il file prima di proseguire.\n",
        $path,
        implode("\n", $output),
    ));
    exit(2);
}

exit(0);
