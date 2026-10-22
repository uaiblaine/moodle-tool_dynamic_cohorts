# BM: an unchanged condition sent back by a category manager records no category.
s{(\$configdata = self::check_condition_scope\(\$condition, \$scope\);\n\s+if \(\$configdata !== null)\)}{$1 && false)}s;
