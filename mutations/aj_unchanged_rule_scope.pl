# AJ: conditions untouched are never checked against the scope.
s{if \(\$scope !== null\) \{\n(\s+)foreach \(\$rule->get_condition_records\(\) as \$condition\) \{}{if (false) \{\n$1foreach (\$rule->get_condition_records() as \$condition) \{}s;
