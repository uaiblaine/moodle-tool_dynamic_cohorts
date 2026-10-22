# K: accept a context of any level.
s{if \(!\$context \|\| !in_array\(\$context->contextlevel, \[CONTEXT_SYSTEM, CONTEXT_COURSECAT\]\)\) \{}{if (!\$context) \{}s;
