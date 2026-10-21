# D: catch nothing, so a TypeError from a condition propagates as it is.
s{\} catch \(\\Throwable \$exception\) \{\n            \$errors = \[\$exception->getMessage\(\)\];\n        \}}{\} finally \{\n        \}}s;
