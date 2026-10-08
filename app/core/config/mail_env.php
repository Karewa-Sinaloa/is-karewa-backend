<?php
/**
 * Overlays the documented mail environment variables onto the `mailing`
 * configuration object.
 *
 * A variable that is present in the environment replaces the corresponding
 * `app/config.yml` value, even when its value is empty; a variable that is
 * absent leaves the file value untouched. This lets an environment select a
 * different mail target, such as the local Mailpit service, without editing
 * the gitignored configuration file.
 *
 * @param object        $mailing The `mailing` configuration object.
 * @param callable|null $env     Optional environment lookup, `fn(string $name): mixed`.
 *                               `false` and `null` mean "not present". Defaults to `getenv()`.
 * @return object The same object, with the overrides applied.
 */
function apply_mail_env_overrides(object $mailing, ?callable $env = null): object {
	$env = $env ?? static fn(string $name) => getenv($name);
	$map = [
		'host'       => 'MAIL_HOST',
		'port'       => 'MAIL_PORT',
		'security'   => 'MAIL_SECURITY',
		'user'       => 'MAIL_USER',
		'password'   => 'MAIL_PASSWORD',
		'smtp_auth'  => 'MAIL_AUTH',
		'from_email' => 'MAIL_FROM_EMAIL',
		'from_name'  => 'MAIL_FROM_NAME',
	];
	foreach ($map as $setting => $variable) {
		$value = $env($variable);
		if ($value === false || $value === null) {
			continue;
		}
		if ($setting === 'port') {
			$value = (int) $value;
		} elseif ($setting === 'smtp_auth') {
			$value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
		}
		$mailing->{$setting} = $value;
	}
	return $mailing;
}

/**
 * Overlays the stored `smtp_config` row of the `config` table onto a mailing
 * settings object.
 *
 * The row keeps its own key names, so they are mapped to the ones the mailer
 * reads: `pass` becomes the password and `from` the sender address, while the
 * port is cast to an integer. The row carries no `smtp_auth`, `debug` or
 * `from_name`, so authentication is derived from the stored credentials,
 * debugging is switched off and no sender name is configured. A row that is
 * absent or whose value is not a JSON object leaves the object untouched so
 * the caller can keep the file values and record the problem.
 *
 * @param object        $mailing The mailing settings object to overlay.
 * @param string|null   $value   The raw `value` column of the row, or null
 *                               when the row does not exist.
 * @param callable|null $log     Optional recorder of the problem,
 *                               `fn(string $message): void`. Called only when
 *                               the row cannot be used.
 * @return object The same object, with the row applied when usable.
 */
function apply_smtp_config_row(object $mailing, ?string $value, ?callable $log = null): object {
	$row = null;
	if (is_string($value) && $value !== '') {
		$decoded = json_decode($value);
		if (is_object($decoded)) {
			$row = $decoded;
		}
	}
	if ($row === null) {
		if ($log !== null) {
			$log(
				$value === null
					? 'No smtp_config row found in the config table; falling back to app/config.yml'
					: 'The smtp_config value is not a JSON object; falling back to app/config.yml'
			);
		}
		return $mailing;
	}
	$mailing->host       = (string) ($row->host ?? '');
	$mailing->port       = (int) ($row->port ?? 0);
	$mailing->security   = (string) ($row->security ?? '');
	$mailing->user       = (string) ($row->user ?? '');
	$mailing->password   = (string) ($row->pass ?? '');
	$mailing->from_email = (string) ($row->from ?? '');
	$mailing->smtp_auth  = $mailing->user !== '' && $mailing->password !== '';
	$mailing->debug      = 0;
	$mailing->from_name  = '';
	return $mailing;
}

/**
 * Resolves the mailing settings for a single message, applying the decided
 * precedence: documented `MAIL_*` environment variables, then the stored
 * `smtp_config` row, then the `mailing` section of `app/config.yml`.
 *
 * The file layer is read as-is and copied, so `$_config->mailing` never
 * changes; the row is read from `$_apiConfig` on every call, which is what
 * lets an update published through the config API take effect on the next
 * message without a restart.
 *
 * @param callable|null $env Optional environment lookup, `fn(string $name): mixed`.
 *                           `false` and `null` mean "not present". Defaults to `getenv()`.
 * @param callable|null $log Optional recorder invoked with a message when the
 *                           row cannot be used. Defaults to `error_logs()`.
 * @return object A fresh settings object with the precedence applied.
 */
function resolve_mailing_settings(?callable $env = null, ?callable $log = null): object {
	global $_config, $_apiConfig;
	$file = $_config->mailing ?? null;
	$mailing = is_object($file) ? clone $file : new stdClass();
	if ($log === null) {
		$log = static function (string $message): void {
			if (function_exists('error_logs')) {
				error_logs([defined('MODULE') ? MODULE : 'mail', $message, __FILE__, __LINE__]);
			}
		};
	}
	$row = $_apiConfig->smtp_config ?? null;
	$mailing = apply_smtp_config_row($mailing, is_string($row) ? $row : null, $log);
	return apply_mail_env_overrides($mailing, $env);
}
?>
