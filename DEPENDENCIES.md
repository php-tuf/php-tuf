# PHP-TUF dependency information

## Production dependencies

### Paragon IE sodium_compat
- **Repository:** https://github.com/paragonie/sodium_compat
- **Release cycle:** No formal policy documented. Follows semver. Old major
  and minor versions appear to receive support after new versions are released.
- **Security policies:**
  [Paragon security
  policy](https://github.com/paragonie/random_compat/security/policy)
  *(NB: **Full disclosure**)*
- **Security issue reporting:** `scott@paragonie.com`
- **Contacts:** ?
- **Additional dependencies:** [random_compat](https://github.com/paragonie/random_compat)
  (Same policies.)

## Development dependencies

### PHPUnit
- **Repository:** https://github.com/sebastianbergmann/phpunit
- **Release cycle:** [Supported versions of
  PHPUnit](https://phpunit.de/supported-versions.html)
- **Security policies:** PHPUnit maintainers consider the package a
  development tool that should not be used in production; therefore, they do
  not have a security release process.
- **Security issue reporting:** N/A
- **Contacts:** N/A
- **Additional dependencies:** PHPUnit adds numerous additional dependencies
  to dev builds. The majority are other packages maintained by PHPUnit or its
  author.

### Symfony PHPUnit Bridge
- **Repository:** https://github.com/symfony/phpunit-bridge
- **Release cycle:** [Symfony releases](https://symfony.com/releases)
  (Scheduled releases, continuous upgrade path, overlapping major and minor
  support, and long-term support versions.)
- **Security policies:** [Symfony security
  policy](https://symfony.com/doc/master/contributing/code/security.html)
- **Security issue reporting:** `security [at] symfony.com`
- **Contacts:** fabpot, michaelcullum
- **Additional dependencies:** None

### PHP_CodeSniffer
- **Repository:** https://github.com/squizlabs/PHP_CodeSniffer
- **Release cycle:** No formal policy documented. Follows semver. Old
  major versions appear to be supported alongside new. Old minor versions
  do not appear to receive support.
- **Security policies:** None listed
- **Security issue reporting:** ?
- **Contacts:** ?
- **Additional dependencies:** None

