#!/bin/bash

curl -s -u "sqp_8adce27e818f5519fd248636cacbeb2ed414b999:"   "https://sonar.enzo-palermo.com/api/issues/search?componentKeys=Enzobu_GMAO_81882c00-d927-4c30-85f8-a0a5a8983d34&resolved=false&ps=500" | jq '.issues[] | {
    file: .component,
    line: .line,
    severity: .severity,
    type: .type,
    rule: .rule,
    message: .message,
    debt: .effort,
    key: .key
}' > sonar-issues.json
