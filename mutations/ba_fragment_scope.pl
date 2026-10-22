# BA: the fragment builds the condition form without a scope.
s{\$customdata = \['classname' => \$classname, 'scope' => rule_access::condition_scope\(\$context\)\];\n    \$mform = new condition_form}{\$customdata = ['classname' => \$classname, 'scope' => null];\n    \$mform = new condition_form}s;
