# BL: a rule moved by a category manager keeps its conditions' old recorded category.
s{(if \(\$configdata !== null && \$configdata !== \$condition->get\('configdata'\))\) \{}{$1 && false) \{}s;
