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

if (!is_string($path) || !str_ends_with($path, '.php') || !is_file($path)) {
    exit(0);
}

exec(sprintf('php -l %s 2>&1', escapeshellarg($path)), $output, $code);

if ($code !== 0) {
    fwrite(STDERR, sprintf(
        "worky: errore di sintassi PHP in %s\n%s\nCorreggi il file prima di proseguire.\n",
        $path,
        implode("\n", $output),
    ));
    exit(2);
}

exit(0);
