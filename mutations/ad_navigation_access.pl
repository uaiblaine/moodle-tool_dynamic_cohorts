# AD: add the navigation node for anyone.
s{\n        if \(!rule_access::can_manage\(\$context\)\) \{\n            return;\n        \}\n}{\n}s;
