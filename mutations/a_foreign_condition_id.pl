# A: drop the check that a submitted condition id belongs to the rule being saved.
s{\n            if \(\$id !== 0 && !array_key_exists\(\$id, \$ruleconditions\)\) \{\n.*?\n            \}\n}{\n}s;
