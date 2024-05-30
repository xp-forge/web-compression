Web compression
===============

[![Build status on GitHub](https://github.com/xp-forge/web-compression/workflows/Tests/badge.svg)](https://github.com/xp-forge/web-compression/actions)
[![XP Framework Module](https://raw.githubusercontent.com/xp-framework/web/master/static/xp-framework-badge.png)](https://github.com/xp-framework/core)
[![BSD Licence](https://raw.githubusercontent.com/xp-framework/web/master/static/licence-bsd.png)](https://github.com/xp-framework/core/blob/master/LICENCE.md)
[![Requires PHP 7.4+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-7_4plus.svg)](http://php.net/)
[![Supports PHP 8.0+](https://raw.githubusercontent.com/xp-framework/web/master/static/php-8_0plus.svg)](http://php.net/)
[![Latest Stable Version](https://poser.pugx.org/xp-forge/web-compression/version.png)](https://packagist.org/packages/xp-forge/web-compression)

[HTTP compression](https://developer.mozilla.org/en-US/docs/Web/HTTP/Compression) for the XP Framework, implemented as filter.

Example
-------
The following shows how to enable on-the-fly compression for HTTP responses. Depending on the *Accept-Encoding* header the client sends, the server-supported compression algorithms and the length of the content, the response is compressed, saving bandwidth.

```php
use web\{Application, Filters};
use web\filters\CompressResponses;

class Service extends Application {

  public function routes() {
    return new Filters([new CompressResponses()], function($req, $res) {
      $content= /* ... */;

      $res->answer(200);
      $res->send($content, 'text/html; charset=utf-8');
    });
  }
}
```