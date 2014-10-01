<?php

namespace Adfero\Build;

interface Testable {
  public function test();
  public function generateTestResultsHTML();
}