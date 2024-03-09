<?php

class List {
   public int $a = 1;
   public function __construct(int $a) {
      $this->a = $a;
   }
   public new(int $a) : List {
      return new List($a);
   }
}

echo 'test-s';

$l = new List(10);
echo $l->a;

$l = List::new(11);
echo $l->a;

echo 'test-e';
