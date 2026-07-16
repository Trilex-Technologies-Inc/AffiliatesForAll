<?php

function installer_value($name, $default = '') {
    return isset($_POST[$name]) ? $_POST[$name] : $default;
}

function installer_request_method() {
    return isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
}

function installer_checked($name, $default = false) {
    if(installer_request_method() != 'POST')
        return $default;

    return isset($_POST[$name]);
}

function installer_php_string($value) {
    return var_export($value, true);
}

function installer_secret() {
    return sha1(uniqid('', true) . mt_rand() . microtime(true));
}

function installer_terms($value) {
    return str_replace("\nAFA_TERMS;\n", "\nAFA_TERMS ;\n", $value);
}

function installer_config($values) {
    $db_dsn = installer_database_dsn($values);

    $lifetime = $values['lifetime_revenue_share'] ? 'TRUE' : 'FALSE';
    $terms = installer_terms($values['terms_of_business']);

    return "<?php\n\n" .
        "\$affiliate_referrer_parameter = " . installer_php_string($values['affiliate_referrer_parameter']) . ";\n" .
        "\$affiliate_data_parameter = " . installer_php_string($values['affiliate_data_parameter']) . ";\n" .
        "\$affiliate_cookie = " . installer_php_string($values['affiliate_cookie']) . ";\n" .
        "\$cookie_lifetime = " . (int) $values['cookie_lifetime'] . " /* days */;\n" .
        "\$cookie_domain = " . installer_php_string($values['cookie_domain']) . ";\n\n" .
        "\$currency = " . installer_php_string($values['currency']) . ";\n" .
        "\$currency_code = " . installer_php_string($values['currency_code']) . ";\n\n" .
        "\$store_home = " . installer_php_string($values['store_home']) . ";\n\n" .
        "\$commission_percent = " . (float) $values['commission_percent'] . ";\n" .
        "\$commission_fixed = " . installer_php_string($values['commission_fixed']) . ";\n\n" .
        "\$affiliate_programme_name = " . installer_php_string($values['affiliate_programme_name']) . ";\n\n" .
        "\$lifetime_revenue_share = $lifetime;\n\n" .
        "\$notification_email_address = " . installer_php_string($values['notification_email_address']) . ";\n" .
        "\$administrator_email_address = " . installer_php_string($values['administrator_email_address']) . ";\n\n" .
        "\$order_fields_available =\n" .
        "    'id, status, customer_id, customer_name, customer_email, total, ' .\n" .
        "    'commission, date_entered, affiliate_data';\n" .
        "\$order_fields_headings =\n" .
        "    'Order Number, Status, Cust ID, Cust Name, Cust Email, Total, ' .\n" .
        "    'Commission, Order Date, Campaign Data';\n\n" .
        "\$rpc_secret = " . installer_php_string($values['rpc_secret']) . ";\n\n" .
        "\$database_dsn = " . installer_php_string($db_dsn) . ";\n" .
        "\$database_username = " . installer_php_string($values['database_username']) . ";\n" .
        "\$database_password = " . installer_php_string($values['database_password']) . ";\n\n" .
        "\$session_cookie_name = " . installer_php_string($values['session_cookie_name']) . ";\n\n" .
        "date_default_timezone_set(" . installer_php_string($values['timezone']) . ");\n\n" .
        "\$terms_of_business = <<<AFA_TERMS\n" .
        $terms . "\n" .
        "AFA_TERMS;\n";
}

function installer_database_dsn($values) {
    return 'mysql:dbname=' . $values['database_name'] .
        ';host=' . $values['database_host'];
}

function installer_import_schema($values) {
    $schema_file = dirname(__FILE__) . '/../affiliates.sql';

    if(!file_exists($schema_file))
        return 'Could not find affiliates.sql.';

    $schema = file_get_contents($schema_file);
    if($schema === false)
        return 'Could not read affiliates.sql.';

    try {
        $pdo = new PDO(
            installer_database_dsn($values),
            $values['database_username'],
            $values['database_password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec($schema);

        $statement = $pdo->prepare(
            'UPDATE affiliates SET local_username = ?, local_password = ? ' .
            'WHERE administrator = true'
        );
        $statement->execute(array(
            $values['administrator_username'],
            $values['administrator_password']
        ));
    } catch(PDOException $ex) {
        return 'Could not import affiliates.sql: ' . $ex->getMessage();
    }

    return false;
}

$config_file = dirname(__FILE__) . '/../config.inc';
$config_exists = file_exists($config_file);
$success = false;
$schema_imported = false;
$errors = array();

$defaults = array(
    'affiliate_programme_name' => 'Affiliates for All',
    'store_home' => 'http://www.example.com/store',
    'administrator_email_address' => '',
    'notification_email_address' => '',
    'currency' => '$',
    'currency_code' => 'USD',
    'commission_percent' => '10',
    'commission_fixed' => '1.00',
    'lifetime_revenue_share' => true,
    'affiliate_referrer_parameter' => 'ref',
    'affiliate_data_parameter' => 'data',
    'affiliate_cookie' => 'affiliate',
    'cookie_lifetime' => '30',
    'cookie_domain' => '',
    'rpc_secret' => installer_secret(),
    'database_host' => '127.0.0.1',
    'database_name' => 'affiliates',
    'database_username' => 'affiliates',
    'database_password' => '',
    'administrator_username' => 'Admin',
    'administrator_password' => '',
    'administrator_password_confirm' => '',
    'import_schema' => true,
    'session_cookie_name' => 'AfASESSID',
    'timezone' => 'Europe/London',
    'terms_of_business' => '<p>Terms of business go here, with HTML markup.</p>'
);

$values = $defaults;
foreach($defaults as $key => $default) {
    if($key == 'lifetime_revenue_share' || $key == 'import_schema') {
        $values[$key] = installer_checked($key, $default);
    } else {
        $values[$key] = installer_value($key, $default);
    }
}

if(installer_request_method() == 'POST') {
    $required = array(
        'affiliate_programme_name' => 'Programme name',
        'store_home' => 'Store home URL',
        'administrator_email_address' => 'Administrator email',
        'database_host' => 'Database host',
        'database_name' => 'Database name',
        'database_username' => 'Database username',
        'rpc_secret' => 'RPC secret',
        'session_cookie_name' => 'Session cookie name',
        'timezone' => 'Timezone'
    );

    foreach($required as $field => $label) {
        if(trim($values[$field]) == '')
            $errors[] = "$label is required.";
    }

    if($config_exists && !isset($_POST['overwrite_config']))
        $errors[] = 'config.inc already exists. Tick overwrite if you want to replace it.';

    if(!is_numeric($values['cookie_lifetime']) || (int) $values['cookie_lifetime'] <= 0)
        $errors[] = 'Cookie lifetime must be a positive number of days.';

    if(!is_numeric($values['commission_percent']))
        $errors[] = 'Commission percent must be numeric.';

    if(!is_numeric($values['commission_fixed']))
        $errors[] = 'Fixed commission must be numeric.';

    if($values['import_schema']) {
        if(trim($values['administrator_username']) == '')
            $errors[] = 'Administrator username is required when importing the schema.';

        if(strlen($values['administrator_username']) > 20)
            $errors[] = 'Administrator username must be 20 characters or fewer.';

        if($values['administrator_password'] == '')
            $errors[] = 'Administrator password is required when importing the schema.';

        if($values['administrator_password'] !== $values['administrator_password_confirm'])
            $errors[] = 'Administrator passwords do not match.';
    }

    if(count($errors) == 0) {
        $config = installer_config($values);
        if(file_put_contents($config_file, $config) === false) {
            $errors[] = 'Could not write config.inc. Check directory permissions.';
        } else {
            $config_exists = true;
            if($values['import_schema']) {
                $schema_error = installer_import_schema($values);
                if($schema_error === false) {
                    $schema_imported = true;
                    $success = true;
                } else {
                    $errors[] = $schema_error;
                }
            } else {
                $success = true;
            }
        }
    }
}

?><!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="site.css">
    <title>Affiliates for All Installer</title>
  </head>
  <body class="auth-body">
    <main class="installer-page">
      <section class="installer-panel">
        <div class="installer-heading">
          <div class="brand-mark">A</div>
          <div>
            <h1>Affiliates for All Installer</h1>
            <p>Create the local <code>config.inc</code> file for this installation.</p>
          </div>
        </div>

        <?php if($success) { ?>
          <div class="alert alert-success">
            <strong>Install complete.</strong> Config created<?php echo $schema_imported ? ' and affiliates.sql imported' : '' ?>. You can open the app now.
          </div>
        <?php } ?>

        <?php if(count($errors) > 0) { ?>
          <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul>
              <?php foreach($errors as $error) { ?>
                <li><?php echo htmlspecialchars($error) ?></li>
              <?php } ?>
            </ul>
          </div>
        <?php } ?>

        <?php if($config_exists && !$success) { ?>
          <div class="alert alert-warning">
            <strong>config.inc already exists.</strong> This installer will not overwrite it unless you tick the overwrite option.
          </div>
        <?php } ?>

        <form method="post" class="installer-form">
          <fieldset class="installer-card">
            <legend>Programme</legend>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label" for="affiliate_programme_name">Programme name</label>
                <input id="affiliate_programme_name" class="form-control" name="affiliate_programme_name" value="<?php echo htmlspecialchars($values['affiliate_programme_name']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="store_home">Store home URL</label>
                <input id="store_home" class="form-control" name="store_home" value="<?php echo htmlspecialchars($values['store_home']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="administrator_email_address">Administrator email</label>
                <input id="administrator_email_address" class="form-control" name="administrator_email_address" value="<?php echo htmlspecialchars($values['administrator_email_address']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label" for="notification_email_address">Notification email</label>
                <input id="notification_email_address" class="form-control" name="notification_email_address" value="<?php echo htmlspecialchars($values['notification_email_address']) ?>">
              </div>
            </div>
          </fieldset>

          <fieldset class="installer-card">
            <legend>Database</legend>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label" for="database_host">Host</label>
                <input id="database_host" class="form-control" name="database_host" value="<?php echo htmlspecialchars($values['database_host']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="database_name">Database</label>
                <input id="database_name" class="form-control" name="database_name" value="<?php echo htmlspecialchars($values['database_name']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="database_username">Username</label>
                <input id="database_username" class="form-control" name="database_username" value="<?php echo htmlspecialchars($values['database_username']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="database_password">Password</label>
                <input id="database_password" class="form-control" type="password" name="database_password" value="<?php echo htmlspecialchars($values['database_password']) ?>">
              </div>
              <div class="col-12 form-check">
                <input id="import_schema" class="form-check-input" type="checkbox" name="import_schema" <?php echo $values['import_schema'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="import_schema">Import affiliates.sql after creating config.inc. This recreates the application tables.</label>
              </div>
            </div>
          </fieldset>

          <fieldset class="installer-card">
            <legend>Administrator Account</legend>
            <p class="text-muted">Choose the credentials used to sign in after the schema is imported.</p>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label" for="administrator_username">Username</label>
                <input id="administrator_username" class="form-control" name="administrator_username" maxlength="20" autocomplete="username" value="<?php echo htmlspecialchars($values['administrator_username']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="administrator_password">Password</label>
                <input id="administrator_password" class="form-control" type="password" name="administrator_password" autocomplete="new-password">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="administrator_password_confirm">Confirm password</label>
                <input id="administrator_password_confirm" class="form-control" type="password" name="administrator_password_confirm" autocomplete="new-password">
              </div>
            </div>
          </fieldset>

          <fieldset class="installer-card">
            <legend>Affiliate Settings</legend>
            <div class="row g-3">
              <div class="col-md-3">
                <label class="form-label" for="currency">Currency symbol</label>
                <input id="currency" class="form-control" name="currency" value="<?php echo htmlspecialchars($values['currency']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="currency_code">Currency code</label>
                <input id="currency_code" class="form-control" name="currency_code" value="<?php echo htmlspecialchars($values['currency_code']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="commission_percent">Commission percent</label>
                <input id="commission_percent" class="form-control" name="commission_percent" value="<?php echo htmlspecialchars($values['commission_percent']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label" for="commission_fixed">Fixed commission</label>
                <input id="commission_fixed" class="form-control" name="commission_fixed" value="<?php echo htmlspecialchars($values['commission_fixed']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="affiliate_referrer_parameter">Referrer parameter</label>
                <input id="affiliate_referrer_parameter" class="form-control" name="affiliate_referrer_parameter" value="<?php echo htmlspecialchars($values['affiliate_referrer_parameter']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="affiliate_data_parameter">Campaign data parameter</label>
                <input id="affiliate_data_parameter" class="form-control" name="affiliate_data_parameter" value="<?php echo htmlspecialchars($values['affiliate_data_parameter']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="affiliate_cookie">Affiliate cookie</label>
                <input id="affiliate_cookie" class="form-control" name="affiliate_cookie" value="<?php echo htmlspecialchars($values['affiliate_cookie']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="cookie_lifetime">Cookie lifetime days</label>
                <input id="cookie_lifetime" class="form-control" name="cookie_lifetime" value="<?php echo htmlspecialchars($values['cookie_lifetime']) ?>">
              </div>
              <div class="col-md-8">
                <label class="form-label" for="cookie_domain">Cookie domain</label>
                <input id="cookie_domain" class="form-control" name="cookie_domain" value="<?php echo htmlspecialchars($values['cookie_domain']) ?>">
              </div>
              <div class="col-12 form-check">
                <input id="lifetime_revenue_share" class="form-check-input" type="checkbox" name="lifetime_revenue_share" <?php echo $values['lifetime_revenue_share'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="lifetime_revenue_share">Enable lifetime revenue share</label>
              </div>
            </div>
          </fieldset>

          <fieldset class="installer-card">
            <legend>System</legend>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label" for="rpc_secret">RPC secret</label>
                <input id="rpc_secret" class="form-control" name="rpc_secret" value="<?php echo htmlspecialchars($values['rpc_secret']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="session_cookie_name">Session cookie</label>
                <input id="session_cookie_name" class="form-control" name="session_cookie_name" value="<?php echo htmlspecialchars($values['session_cookie_name']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label" for="timezone">Timezone</label>
                <input id="timezone" class="form-control" name="timezone" value="<?php echo htmlspecialchars($values['timezone']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label" for="terms_of_business">Terms of business HTML</label>
                <textarea id="terms_of_business" class="form-control" name="terms_of_business" rows="6"><?php echo htmlspecialchars($values['terms_of_business']) ?></textarea>
              </div>
            </div>
          </fieldset>

          <div class="installer-actions">
            <?php if($config_exists) { ?>
              <label class="form-check">
                <input class="form-check-input" type="checkbox" name="overwrite_config">
                <span class="form-check-label">Overwrite existing config.inc</span>
              </label>
            <?php } ?>
            <button class="btn btn-primary" type="submit">Create config.inc</button>
          </div>
        </form>
      </section>
    </main>
  </body>
</html>
