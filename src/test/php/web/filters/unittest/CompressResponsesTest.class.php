<?php namespace web\filters\unittest;

use io\streams\compress\{Algorithms, Gzip};
use lang\IllegalArgumentException;
use test\verify\Runtime;
use test\{Assert, Expect, Test, Values};
use web\filters\CompressResponses;

class CompressResponsesTest {

  #[Test]
  public function can_create() {
    new CompressResponses();
  }

  #[Test]
  public function default_algorithms() {
    Assert::that((new CompressResponses())->algorithms())
      ->mappedBy(fn($a) => $a->token())
      ->isEqualTo(['brotli' => 'br', 'gzip' => 'gzip', 'bzip2' => 'bzip2'])
    ;
  }

  #[Test]
  public function pass_algorithm_names() {
    Assert::that((new CompressResponses(['gzip', 'br']))->algorithms())
      ->mappedBy(fn($a) => $a->token())
      ->isEqualTo(['gzip' => 'gzip', 'brotli' => 'br'])
    ;
  }

  #[Test]
  public function pass_algorithm_instances() {
    Assert::that((new CompressResponses([new Gzip()]))->algorithms())
      ->mappedBy(fn($a) => $a->token())
      ->isEqualTo(['gzip' => 'gzip'])
    ;
  }

  #[Test, Expect(IllegalArgumentException::class)]
  public function unknown_algorithm_name() {
    new CompressResponses(['unknown']);
  }

  #[Test, Runtime(extensions: ['zlib'])]
  public function negotiates_gzip() {
    Assert::equals('gzip', (new CompressResponses())->negotiate('gzip, deflate, br')->name());
  }

  #[Test, Runtime(extensions: ['zlib'])]
  public function negotiates_highest_qfactor() {
    Assert::equals('gzip', (new CompressResponses())->negotiate('br;q=0.9, gzip;q=1.0')->name());
  }

  #[Test, Runtime(extensions: ['zlib'])]
  public function negotiating_any_uses_server_preference() {
    Assert::equals('gzip', (new CompressResponses(['gzip', 'br']))->negotiate('*')->name());
  }

  #[Test]
  public function explicitely_excluded_encoding() {
    Assert::null((new CompressResponses())->negotiate('gzip;q=0'));
  }

  #[Test]
  public function negotiating_identity() {
    Assert::null((new CompressResponses())->negotiate('identity'));
  }

  #[Test]
  public function negotiating_unsupported() {
    Assert::null((new CompressResponses())->negotiate('unsupported'));
  }

  #[Test, Values(['', null])]
  public function negotiating_empty($header) {
    Assert::null((new CompressResponses())->negotiate($header));
  }
}