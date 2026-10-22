# S: an existing rule's form opens in the page's context too.
s{\n        if \(!empty\(\$ruleid\)\) \{\n            return rule_access::context_for\(rule::get_record\(\['id' => \$ruleid\]\) \?: null\);\n        \}\n}{\n}s;
