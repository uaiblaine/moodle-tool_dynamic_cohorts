# EB: A stored role condition with an unknown context level matches the role held anywhere.
s{            if \(\!in_array\(\$this\-\>get_contextlevel_value\(\)\, self\:\:CONTEXT_LEVELS\, true\)\) \{\n                return true\;\n            \}\n}{}s;
