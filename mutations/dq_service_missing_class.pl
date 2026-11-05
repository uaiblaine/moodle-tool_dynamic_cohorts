# DQ: The conditions web service returns the stored JSON of a condition whose class is gone raw.
s{\$description \= s\(\$condition\-\>get\(\'configdata\'\)\)\;}{\$description = \$condition->get('configdata');}s;
