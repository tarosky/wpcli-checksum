Feature: Validate checksums for WordPress install

  @require-php-5.6
  Scenario: Verify core checksums
    Given a WP install

    When I run `wp core update`
    Then STDOUT should not be empty

    When I run `wp tarosky checksum core`
    Then the return code should be 0
    And STDOUT should be:
      """
      {"verified":true}
      """
    And STDERR should be empty

  Scenario: Core checksums don't verify
    Given a WP install
    And "WordPress" replaced with "Wordpress" in the readme.html file

    When I try `wp tarosky checksum core`
    Then the return code should be 1
    And STDOUT should be:
      """
      {"verified":false,"mismatch":["readme.html"]}
      """
    And STDERR should be empty

    When I run `rm readme.html`
    Then STDERR should be empty

    When I try `wp tarosky checksum core`
    And STDOUT should be:
      """
      {"verified":false,"missing":["readme.html"]}
      """

  Scenario: Core checksums don't verify because wp-cli.yml is present
    Given a WP install
    And a wp-cli.yml file:
      """
      plugin install:
      - user-switching
      """

    When I try `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":false,"added":["wp-cli.yml"]}
      """

    When I run `rm wp-cli.yml`
    Then STDERR should be empty

    When I run `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":true}
      """

  Scenario: Verify core checksums without loading WordPress
    Given an empty directory
    And I run `wp core download --version=4.3`

    When I run `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":true}
      """

    When I run `wp tarosky checksum core --version=4.3 --locale=en_US`
    Then STDOUT should be:
      """
      {"verified":true}
      """

    When I try `wp tarosky checksum core --version=4.2 --locale=en_US`
    Then STDOUT should contain:
      """
      "verified":false
      """

  Scenario: Verify core checksums for a non US local
    Given an empty directory
    And I run `wp core download --locale=en_GB --version=4.3.1 --force`
    Then STDOUT should contain:
      """
      Success: WordPress downloaded.
      """
    And the return code should be 0

    When I run `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":true}
      """

  @require-php-5.6
  Scenario: Verify core checksums with extra files
    Given a WP install

    When I run `wp core update`
    Then STDOUT should not be empty

    Given a wp-includes/extra-file.txt file:
      """
      hello world
      """
    Then the wp-includes/extra-file.txt file should exist

    When I try `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":false,"added":["wp-includes\/extra-file.txt"]}
      """

  Scenario: Verify core checksums when extra files prefixed with 'wp-' are included in WordPress root
    Given a WP install
    And a wp-extra-file.php file:
      """
      hello world
      """

    When I try `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":false,"added":["wp-extra-file.php"]}
      """

  Scenario: Verify core checksums when extra files are included in WordPress root and --include-root is passed
    Given a WP install
    And a .htaccess file:
      """
      # BEGIN WordPress
      """
    And a extra-file.php file:
      """
      hello world
      """
    And a unknown-folder/unknown-file.php file:
      """
      taco burrito
      """
    And a wp-content/unknown-file.php file:
      """
      foobar
      """

    When I try `wp tarosky checksum core --include-root`
    Then STDOUT should be:
      """
      {"verified":false,"added":["unknown-folder\/unknown-file.php","extra-file.php"]}
      """

    When I run `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":true}
      """

  Scenario: Verify core checksums with a plugin that has wp-admin
    Given a WP install
    And a wp-content/plugins/akismet/wp-admin/extra-file.txt file:
      """
      hello world
      """

    When I run `wp tarosky checksum core`
    Then STDOUT should be:
      """
      {"verified":true}
      """
