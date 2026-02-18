#!/bin/bash

# GWN Client Edit API Curl Test Script

echo "========================================="
echo "GWN Client Edit API - Curl Tests"
echo "========================================="
echo ""

# Test 1: Valid MAC
echo "TEST 1: Valid MAC with name change"
echo "Endpoint: POST /oapi/v1.0.0/client/edit"
echo "Parameters: clientId=20:EE:28:86:C2:ED, name=TestClient123"
echo ""
echo "Response:"
curl -s -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=103592&timestamp=1771088942435&signature=61a8e2dde9921171ee09e67029ffacf474340406c97a5a97da03fba0a0baa5d4' \
  -H 'Content-Type: application/json' \
  -d '{"clientId":"20:EE:28:86:C2:ED","name":"TestClient123"}' | jq .
echo ""
echo "---"
echo ""

# Test 2: Invalid MAC
echo "TEST 2: Invalid MAC (too short - 11:22:33:44)"
echo "Endpoint: POST /oapi/v1.0.0/client/edit"
echo "Error Expected: 50007 (invalid mac)"
echo ""
echo "Response:"
curl -s -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=103592&timestamp=1771088942435&signature=630cc445e3999334b1e0521978aee51f1954b724c459152de2bc75ec7a78cc60' \
  -H 'Content-Type: application/json' \
  -d '{"clientId":"11:22:33:44","name":"TestClient"}' | jq .
echo ""
echo "---"
echo ""

# Test 3: Name too long
echo "TEST 3: Name exceeding 64 characters"
echo "Endpoint: POST /oapi/v1.0.0/client/edit"
echo "Error Expected: 40004 (client length error)"
echo ""
echo "Response:"
curl -s -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=103592&timestamp=1771088942435&signature=757a27d3e132fc8ff2558fcd5f551237897bad2cf604aa2841eede0331cb0a8e' \
  -H 'Content-Type: application/json' \
  -d '{"clientId":"20:EE:28:86:C2:ED","name":"AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA"}' | jq .
echo ""
echo "---"
echo ""

# Test 4: Non-existent MAC
echo "TEST 4: Non-existent MAC (FF:FF:FF:FF:FF:FF)"
echo "Endpoint: POST /oapi/v1.0.0/client/edit"
echo "Error Expected: 50004 (Service error - client not on network)"
echo ""
echo "Response:"
curl -s -X POST 'https://www.gwn.cloud/oapi/v1.0.0/client/edit?appID=103592&timestamp=1771088942435&signature=d3bf39398d0d7f2b03baa72f5cade4922a5816b342f5c42ae8e5a94357135e54' \
  -H 'Content-Type: application/json' \
  -d '{"clientId":"FF:FF:FF:FF:FF:FF","name":"NonExistentClient"}' | jq .
echo ""

echo "========================================="
echo "Tests Complete"
echo "========================================="
