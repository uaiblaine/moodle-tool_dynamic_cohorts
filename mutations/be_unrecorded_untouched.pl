# BE: a condition without a recorded category goes on to the recheck.
s{if \(!is_array\(\$config\) \|\| empty\(\$config\[self::SCOPE_KEY\]\)\) \{}{if (false) \{}s;
