#!/bin/sh

set -e

scriptDir="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd $scriptDir

redcapPath=`php ./get-redcap-version-path.php`
cd $redcapPath

remoteName=`git remote -v|grep 'git@github.com:vanderbilt/REDCap.git (fetch)'|cut -f 1`
git fetch $remoteName

methodName=$1
if [ -z $methodName ]; then
    echo "You must specify a method name."
    exit
fi

# We want the commit where the method was added to the docs, NOT the commit where it was added to the framework.
# Sometimes methods are added in a partially completed state before they are finished and documented.
commit=`git log -G "^$methodName\(" --pretty=oneline --reverse -- ExternalModules/docs/ | head -n 1 | cut -c 1-7`

if [ -z $commit ]; then
    echo "Could not find any commits for the $methodName() method."
    exit
fi

git show -p $commit -- ExternalModules/docs/

redcapVersion=`git name-rev --tags --name-only $commit | cut -d'~' -f1`

echo 
echo "You just reviewed commit $commit.  If it was indeed the correct commit when the $methodName() method was added, then the minimum REDCap version for it is $redcapVersion."
echo