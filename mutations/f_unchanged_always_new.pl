# F: treat every submitted condition as new input.
s{\$unchanged = \$stored\n}{\$unchanged = false && \$stored\n}s;
