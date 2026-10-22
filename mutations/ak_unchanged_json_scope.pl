# AK: a stored condition sent back unchanged is not checked against the scope.
s{\n            \} else if \(\$scope !== null\) \{\n.*?\n                self::check_condition_scope\(\$condition, \$scope\);\n            \}\n}{\n            \}\n}s;
