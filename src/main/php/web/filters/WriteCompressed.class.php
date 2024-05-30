<?php namespace web\filters;

use io\streams\Compression;
use web\io\Output;

/**
 * Writes compressed data
 *
 * @see  https://stackoverflow.com/questions/9871798/how-to-only-compress-documents-above-150-bytes-served-by-apache
 * @see  https://stackoverflow.com/questions/56398016/what-is-the-optimum-minimum-length-for-brotli-compression
 * @test web.filters.unittest.HandlingTest
 */
class WriteCompressed extends Output {
  private $response, $compression, $threshold, $status, $message, $headers;
  private $target= null;
  private $stream= null;
  private $buffer= '';

  /**
   * Creates a new compressed output
   *
   * @param  web.Response $response
   * @param  io.streams.compressed.Algorithm $compression
   * @param  int $threshold
   */
  public function __construct($response, $compression, $threshold= 500) {
    $this->response= $response;
    $this->compression= $compression;
    $this->threshold= $threshold;
  }

  /** Stop buffering and start compressing */
  private function start() {
    $this->target= $this->response->output()->stream();
    $this->target->begin($this->status, $this->message, $this->headers + [
      'Vary'             => ['Accept-Encoding'],
      'Content-Encoding' => [$this->compression->token()],
    ]);

    $this->stream= $this->compression->create($this->target, Compression::FASTEST);
    $this->stream->write($this->buffer);
    $this->buffer= null;
  }

  /** Wait with flushing headers until we know if we should compress */
  public function begin($status, $message, $headers) {
    $this->status= $status;
    $this->message= $message;
    $this->headers= $headers;
  }

  /** Buffer data until we reach the threshold (or flush() is explicitely called) */
  public function write($chunk) {
    if ($this->stream) {
      $this->stream->write($chunk);
    } else {
      $this->buffer.= $chunk;
      if (strlen($this->buffer) > $this->threshold) $this->start();
    }
  }

  /** Start compressing if explicitely flushed */
  public function flush() {
    $this->stream ?? $this->start();
    $this->stream->flush();
  }

  /** Write uncompressed data if threshold hasn't been unreached */
  public function finish() {
    if ($this->stream) {
      $this->response->trace('compression', $this->compression->name());
      $this->stream->close();
    } else {
      $this->response->trace('compression', '(skipped)');
      $this->target= $this->response->output();
      $this->target->begin($this->status, $this->message, $this->headers + [
        'Content-Length' => [strlen($this->buffer)],
      ]);
      $this->target->write($this->buffer);
    }

    $this->target->close();
  }
}