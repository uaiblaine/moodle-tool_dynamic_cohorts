# AH: open the rules report at any context level the user holds the capabilities in.
s{return in_array\(\$context->contextlevel, \[CONTEXT_SYSTEM, CONTEXT_COURSECAT\], true\)\n            && rule_access::can_manage\(\$context\);}{return rule_access::can_manage(\$context);}s;
