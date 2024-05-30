<?php namespace web\filters\unittest;

use io\streams\compress\Algorithm;
use io\streams\{Compression, InputStream, OutputStream};
use test\{Assert, Before, Test, Values};
use web\filters\CompressResponses;
use web\io\{TestInput, TestOutput};
use web\{Filters, Request, Response};

class HandlingTest {
  private $algorithm, $payload;

  /**
   * Handles a request with the given headers and handler function,
   * returning the response.
   *
   * @param  [:string] $headers
   * @param  function(web.Request, web.Response): var $handler
   * @return web.Response
   */
  private function handle($headers, $handler) {
    $compress= new Filters([new CompressResponses([$this->algorithm])], $handler);
    $req= new Request(new TestInput('GET', '/', $headers));
    $res= new Response(new TestOutput());

    foreach ($compress->handle($req, $res) ?? [] as $_) { }
    return $res;
  }

  /** @return iterable */
  private function write() {
    yield ['sending', fn($content) => function($req, $res) use($content) {
      $res->send($content, 'text/plain; charset=utf-8');
    }];
    yield ['streaming', fn($content) => function($req, $res) use($content) {
      $res->header('Content-Type', 'text/plain; charset=utf-8');
      $stream= $res->stream();
      $stream->write($content);
      $stream->close();
    }];
  }

  #[Before]
  public function payload() {
    $this->payload= str_repeat('*', 512); // Larger than default threshold of 500 bytes
  }

  #[Before]
  public function algorithm() {
    $this->algorithm= new class() extends Algorithm {
      public function supported(): bool { return true; }
      public function name(): string { return 'test'; }
      public function token(): string { return 'test'; }
      public function extension(): string { return '.test'; }
      public function level(int $select): int { return $select; }
      public function open(InputStream $in): InputStream { return $in; }
      public function create(OutputStream $out, int $method= Compression::DEFAULT): OutputStream {
        return new class($out) implements OutputStream {
          private $target;
          public function __construct($target) { $this->target= $target; }
          public function write($chunk) { $this->target->write('<compressed:'.strlen($chunk).'>'); }
          public function flush() { $this->target->flush(); }
          public function close() { $this->target->close(); }
        };
      }
    };
  }

  #[Test]
  public function sends_content_uncompressed_by_default() {
    $res= $this->handle([], function($req, $res) {
      $res->send($this->payload, 'text/plain; charset=utf-8');
    });

    Assert::equals(
      "HTTP/1.1 200 OK\r\n".
      "Content-Type: text/plain; charset=utf-8\r\n".
      "Content-Length: 512\r\n".
      "\r\n".
      $this->payload,
      $res->output()->bytes()
    );
  }

  #[Test]
  public function streams_content_uncompressed_by_default() {
    $res= $this->handle([], function($req, $res) {
      $res->header('Content-Type', 'text/plain; charset=utf-8');
      $stream= $res->stream();
      $stream->write($this->payload);
      $stream->close();
    });

    Assert::equals(
      "HTTP/1.1 200 OK\r\n".
      "Content-Type: text/plain; charset=utf-8\r\n".
      "Transfer-Encoding: chunked\r\n".
      "\r\n".
      "200\r\n{$this->payload}\r\n0\r\n\r\n",
      $res->output()->bytes()
    );
  }

  #[Test]
  public function streams_and_compresses_content_when_flushed() {
    $res= $this->handle(['Accept-Encoding' => 'test'], function($req, $res) {
      $res->header('Content-Type', 'text/plain; charset=utf-8');
      $stream= $res->stream();
      $stream->write('Test');
      $stream->flush();
      $stream->close();
    });

    Assert::equals(
      "HTTP/1.1 200 OK\r\n".
      "Content-Type: text/plain; charset=utf-8\r\n".
      "Vary: Accept-Encoding\r\n".
      "Content-Encoding: test\r\n".
      "Transfer-Encoding: chunked\r\n".
      "\r\n".
      "e\r\n<compressed:4>\r\n0\r\n\r\n",
      $res->output()->bytes()
    );
  }

  #[Test, Values(from: 'write')]
  public function compresses_when($action, $write) {
    Assert::equals(
      "HTTP/1.1 200 OK\r\n".
      "Content-Type: text/plain; charset=utf-8\r\n".
      "Vary: Accept-Encoding\r\n".
      "Content-Encoding: test\r\n".
      "Transfer-Encoding: chunked\r\n".
      "\r\n".
      "10\r\n<compressed:512>\r\n0\r\n\r\n",
      $this->handle(['Accept-Encoding' => 'test'], $write($this->payload))->output()->bytes()
    );
  }

  #[Test, Values(from: 'write')]
  public function does_not_compress_below_threshold_when($action, $write) {
    Assert::equals(
      "HTTP/1.1 200 OK\r\n".
      "Content-Type: text/plain; charset=utf-8\r\n".
      "Content-Length: 4\r\n".
      "\r\n".
      "Test",
      $this->handle(['Accept-Encoding' => 'test'], $write('Test'))->output()->bytes()
    );
  }
}