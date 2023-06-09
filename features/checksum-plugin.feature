Feature: Validate checksums for WordPress plugins

  Scenario: Verify plugin checksums
    Given a WP install

    When I run `wp plugin install duplicate-post --version=3.2.1`
    Then STDOUT should not be empty
    And STDERR should be empty

    When I run `wp tarosky checksum plugins duplicate-post`
    Then the return code should be 0
    And STDOUT should be:
      """
      {"name":"duplicate-post","verified":true}
      """
    And STDERR should be empty

    When I run `wp tarosky checksum plugins duplicate-post --version=3.2.1`
    Then STDOUT should be:
      """
      {"name":"duplicate-post","verified":true}
      """
    And STDERR should be empty

    When I try `wp tarosky checksum plugins duplicate-post --version=3.2.2`
    Then the return code should be 1
    And STDOUT should be:
      """
      {"name":"duplicate-post","verified":false,"mismatch":["duplicate-post-admin.php","duplicate-post-common.php","duplicate-post-options.php","duplicate-post.css","duplicate-post.php"],"missing":["duplicate-post-jetpack.php","duplicate-post-wpml.php"]}
      """
    And STDERR should be empty

  Scenario: Modified plugin doesn't verify
    Given a WP install

    When I run `wp plugin install duplicate-post --version=3.2.1`
    Then STDOUT should not be empty
    And STDERR should be empty

    Given "Duplicate Post" replaced with "Different Name" in the wp-content/plugins/duplicate-post/duplicate-post.php file

    When I try `wp tarosky checksum plugins duplicate-post`
    Then STDOUT should be:
      """
      {"name":"duplicate-post","verified":false,"mismatch":["duplicate-post.php"]}
      """

    When I run `rm wp-content/plugins/duplicate-post/duplicate-post.css`
    Then STDERR should be empty

    When I try `wp tarosky checksum plugins duplicate-post`
    Then STDOUT should be:
      """
      {"name":"duplicate-post","verified":false,"mismatch":["duplicate-post.php"],"missing":["duplicate-post.css"]}
      """

    When I run `touch wp-content/plugins/duplicate-post/additional-file.php`
    Then STDERR should be empty

    When I try `wp tarosky checksum plugins duplicate-post`
    Then STDOUT should be:
      """
      {"name":"duplicate-post","verified":false,"added":["additional-file.php"],"mismatch":["duplicate-post.php"],"missing":["duplicate-post.css"]}
      """

  Scenario: Soft changes are only reported in strict mode
    Given a WP install

    When I run `wp plugin install release-notes --version=0.1`
    Then STDOUT should not be empty
    And STDERR should be empty

    Given "Release Notes" replaced with "Different Name" in the wp-content/plugins/release-notes/readme.txt file

    When I run `wp tarosky checksum plugins release-notes`
    Then STDOUT should be:
      """
      {"name":"release-notes","verified":true}
      """

    When I try `wp tarosky checksum plugins release-notes --strict`
    Then STDOUT should be:
      """
      {"name":"release-notes","verified":false,"mismatch":["readme.txt"]}
      """

    Given "Release Notes" replaced with "Different Name" in the wp-content/plugins/release-notes/README.md file

    When I run `wp tarosky checksum plugins release-notes`
    Then STDOUT should be:
      """
      {"name":"release-notes","verified":true}
      """

    When I try `wp tarosky checksum plugins release-notes --strict`
    Then STDOUT should be:
      """
      {"name":"release-notes","verified":false,"mismatch":["README.md","readme.txt"]}
      """

  # WPTouch 4.3.22 contains multiple checksums for some of its files.
  # See https://github.com/wp-cli/checksum-command/issues/24
  Scenario: Multiple checksums for a single file are supported
    Given a WP install

    When I run `wp plugin install wptouch --version=4.3.22`
    Then STDOUT should not be empty
    And STDERR should be empty

    When I run `wp tarosky checksum plugins wptouch`
    Then STDOUT should be:
      """
      {"name":"wptouch","verified":true}
      """

  Scenario: Throws an error if provided with neither plugin names nor the --all flag
    Given a WP install

    When I try `wp tarosky checksum plugins`
    Then STDERR should contain:
      """
      You need to specify either one or more plugin slugs to check or use the --all flag to check all plugins.
      """
    And STDOUT should be empty
    And the return code should be 1

  Scenario: Ensure a plugin cannot filter itself out of the checks
    Given a WP install
    And these installed and active plugins:
      """
      duplicate-post
      wptouch
      """
    And a wp-content/mu-plugins/hide-dp-plugin.php file:
      """
      <?php
      /**
       * Plugin Name: Hide Duplicate Post plugin
       */

       add_filter( 'all_plugins', function( $all_plugins ) {
          unset( $all_plugins['duplicate-post/duplicate-post.php'] );
          return $all_plugins;
       } );
      """
    And "Duplicate Post" replaced with "Different Name" in the wp-content/plugins/duplicate-post/duplicate-post.php file

    When I run `wp plugin list --fields=name`
    Then STDOUT should not contain:
      """
      duplicate-post
      """

    When I try `wp tarosky checksum plugins --all`
    Then STDOUT should contain:
      """
      {"name":"duplicate-post","verified":false,"mismatch":["duplicate-post.php"]}
      """
