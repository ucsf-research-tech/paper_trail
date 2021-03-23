#!/bin/sh

if [ ! `git rev-parse --abbrev-ref HEAD` == 'production' ]; then
    echo
    echo 'The production branch is not checked out!  You probably do not want to deploy any other branches.'
    echo 
    exit 1
fi
