# G: any stored condition counts as unchanged, whatever class or config data comes back.
s{\$unchanged = \$stored\n.*?;\n}{\$unchanged = (bool) \$stored;\n}s;
