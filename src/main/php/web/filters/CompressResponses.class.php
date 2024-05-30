<?php namespace web\filters;

use io\streams\compress\{Algorithm, Algorithms, Brotli, Bzip2, Gzip};
use lang\IllegalArgumentException;
use web\{Filter, Headers};

/**
 * Compression
 *
 * @see  https://developer.mozilla.org/en-US/docs/Web/HTTP/Compression
 * @test web.filters.unittest.CompressResponsesTest
 */
class CompressResponses implements Filter {
  private $algorithms;

  /**
   * Creates new filter compressing responses on the fly
   *
   * @param  ?io.streams.compress.Algorithm[]|string[] $algorithms
   */
  public function __construct(?iterable $algorithms= null) {
    static $impl= [
      'br'    => Brotli::class,
      'gzip'  => Gzip::class,
      'bzip2' => Bzip2::class,
    ];

    $this->algorithms= new Algorithms();
    if (null === $algorithms) {
      $this->algorithms->add(new Brotli(), new Gzip(), new Bzip2());
    } else {
      foreach ($algorithms as $algorithm) {
        if ($algorithm instanceof Algorithm) {
          $this->algorithms->add($algorithm);
        } else if ($class= $impl[$algorithm] ?? null) {
          $this->algorithms->add(new $class());
        } else {
          throw new IllegalArgumentException('Unknown algorithm "'.$algorithm.'"');
        }
      }
    }
  }

  /** @return io.streams.compress.Algorithms */
  public function algorithms() { return $this->algorithms; }

  /**
   * Negotiate encoding
   * 
   * @see    https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept-Encoding
   * @param  ?string $accept The value of the `Accept-Encoding` header
   * @param  ?io.streams.compress.Algorithm
   */
  public function negotiate($accept) {
    if (null === $accept) return null;

    foreach (Headers::qfactors()->parse($accept) as $encoding => $q) {
      if (0.0 === $q) {
        continue;
      } else if ('*' === $encoding) {
        return $this->algorithms->supported()->current();
      } else if (($algorithm= $this->algorithms->find($encoding)) && $algorithm->supported()) {
        return $algorithm;
      }
    }

    return null;
  }

  /**
   * Filter request, sending the data back compressed according to the client's
   * preference and server-supported compression algorithms.
   *
   * @param  web.Request
   * @param  web.Response
   * @param  web.filters.Invocation
   * @return var
   */
  public function filter($req, $res, $invocation) {
    if ($compression= $this->negotiate($req->header('Accept-Encoding'))) {
      $res->streaming(fn($res) => new WriteCompressed($res, $compression));
    }

    return $invocation->proceed($req, $res);
  }
}