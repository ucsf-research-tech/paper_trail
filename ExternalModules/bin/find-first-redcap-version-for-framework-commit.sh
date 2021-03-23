#!/bin/sh

set -e

commit=$1
if [ -z $commit ]; then
    echo You must specify a framework commit hash.
    exit
fi

redcapRepo=`ls -1d ~/www/redcap_v* | tail -n 1`
redcapGitDir="--git-dir $redcapRepo/.git"

git $redcapGitDir fetch upstream

firstVersion=''
for tag in $(git $redcapGitDir tag | sort -n | tail -n 10); do
    frameworkCommitForTag=`git $redcapGitDir log $tag -1 --pretty=oneline --no-merges --grep 'Include External Module framework commit' | cut -d' ' -f7`
    if $(git merge-base --is-ancestor $commit $frameworkCommitForTag ); then
        firstVersion=$tag
        break
    fi
done

echo $firstVersion