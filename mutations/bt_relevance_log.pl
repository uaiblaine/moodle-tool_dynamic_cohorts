# BT: a log action counts as relevant.
s#\n        if \(\$action === \\core_competency\\evidence::ACTION_LOG\) \{\n            return false;\n        \}\n#\n#s;
