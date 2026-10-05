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
?>
