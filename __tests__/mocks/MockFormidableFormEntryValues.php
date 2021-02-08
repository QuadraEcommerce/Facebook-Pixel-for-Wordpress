<?php
/*
 * Copyright (C) 2017-present, Facebook, Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; version 2 of the License.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

namespace FacebookPixelPlugin\Tests\Mocks;

final class MockFormidableFormEntryValues {
  private $field_values;
  private $throw;

  public function __construct($field_values) {
    $this->field_values = $field_values;
  }

  public function set_throw($throw) {
    $this->throw = $throw;
  }

  public function get_field_values() {
    if ($this->throw) {
      throw new \Exception('Unable to read field values!');
    }

    return $this->field_values;
  }
}
