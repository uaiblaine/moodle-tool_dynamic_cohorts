# AB: drop the page's access check.
s{\n        rule_access::require_manage\(\$context\);\n\n        return \$context;\n}{\n        return \$context;\n}s;
