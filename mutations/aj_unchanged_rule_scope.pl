# AJ: conditions untouched are never checked against the scope.
s{\n            if \(\$scope !== null\) \{\n                foreach \(\$rule->get_condition_records\(\) as \$condition\) \{\n                    self::check_condition_scope\(\$condition, \$scope\);\n                \}\n            \}\n}{\n}s;
