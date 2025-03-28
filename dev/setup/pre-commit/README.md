## 'pre-commit' ("replaces" precommit)

### Introduction

[`pre-commit`](https://pre-commit.org) is a framework for managing and
maintaining multi-language pre-commit hooks.

"pre-commit hooks" integrate with `git` and are run when you perform a
`git commit` for instance.

Historically there was `precommit` for Dolibarr which you can find in this
directory. That script runs `phplint`, `phpcs` and `phpcbf` upon commit.

`pre-commit` is not specific to Dolibarr and is deployed on many projects -
mostly Python projects, but it is applicable to most (or *all*) code and
documentation development.

You can find documentation at https://pre-commit.com/ .

Now you can use `pre-commit` which uses the configuration found at the root of
the project: `pre-commit-config.yaml`.


### Installation in your git project

If you're running MacOS, you can use [homebrew](https://brew.sh/) as a package manager

1. Install pre-commit tool.\
   If you do not have python installed, install [python](https://www.python.org) first.\
   `sudo apt install python3`
   
   If you do not have [`pip`](https://pypi.org/project/pip), install that as well.\
   `sudo apt install pip`

   Then you can install pre-commit tool:\
   `python3 -m pip install pre-commit`
   or
   `python3 -m pip install pre-commit --break-system-packages`

   Then install phpcbf and phpcs:\
   `sudo apt install php-codesniffer`

   If you're running MacOS, you can follow the steps above by replacing the commands by the following:\
   Install [pipx](https://pipx.pypa.io/latest/installation/): `brew install pipx` and then `pipx ensurepath`\
   Install pre-commit tool: `pipx install pre-commit\
   Install phpcbf and phpcs: `brew install php-codesniffer`\

3. In your local git clone of the project, run `pre-commit install` to add the hooks 
   or copy the file *dev/setup/git/hooks/pre-commit* manually into *.git/hooks/pre-commit*
   (recommended because this file may differ from the file installed with the pre-commit install).
   The good file redirects output to the error channel so your IDE will be able to catch the error.


### Troubleshooting

* If you get error "ModuleNotFoundError: No module named 'platformdirs'"

Install the python package with
`pip3 install platformdirs`   or   `pip3 install platformdirs --break-system-packages`

* If you get error "ModuleNotFoundError: No module named 'pkg_resources'"

Install the python package with
`pip3 install pkg_resources`   or   `pip3 install pkg_resources --break-system-packages`

* If you get error "ERROR: PHP_CodeSniffer requires the tokenizer, xmlwriter and SimpleXML extensions to be enabled. Please enable xmlwriter and SimpleXML."

Install the PHP package xml
`sudo apt install php-simplexml`


### Tips


After installing `pre-commit` onto your local git clone, pre-commit will run
on every commit. The first time, all tools required by pre-commit will be installed
into ~/.cache/pre-commit

When it finds some issue, the git commit will be aborted, so you can fix it,
or verify it.

The tools run by `pre-commit` may modify your code: format PHP code
(`phpcbf`), fix line endings, end of files, etc. If code is modified, the commit
is canceled. Try another commit to do it.

They may also alert about potential issues: syntax errors, spelling errors,
code quality, an execute bit that was (not) set in the git repository, etc.

One way to use it is this:

```bash
cd PROJECT_DIR
pre-commit install  # Only needed once
# Repeat until success.
git commit -a -m "My message"
# `pre-commit` is run and reports
# Check the results, make fixes, and re-commit (=repeat above line).
```

In some cases you may want to commit despite the changes.\
You can just add
`--no-verify` to your git command:

```bash
git commit -a -m "My message" --no-verify
```

If you want to skip certain checks for whatever reason, you can set the SKIP
environment variable into the .git/hooks/pre-commit file:

```bash
export SKIP=no-commit-to-branch
```

You can also switch output to text only, by setting the PRE_COMMIT_COLOR in .git/hooks/pre-commit file:

```bash
export PRE_COMMIT_COLOR=never
```

There is much more you can do with pre-commit, check out its
[documentation](https://pre-commit.com).

Now your commit is less likely to fail in the Continuous Integration (CI) run
on github.\
CI also runs pre-commit to help maintain code quality.

Note:
Code for precommits are saved into:
.cache/pre-commit/repo.../pre_commit_hooks/php-....sh
and
.cache/pre-commit/repo.../py_env-python3/lib/python.../site-packages/pre_commit_hooks/no_commit_to_branch.py
