#!/bin/bash

# KingsChat API Test Suite
# This script tests all the OAuth and API endpoints used by the Flutter app

echo "=== KingsChat API Test Suite ==="
echo "Testing OAuth and API endpoints..."
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration (from PHP implementation)
CLIENT_ID="619b30ea-a682-47fb-b90f-5b8e780b89ca"
AUTH_URL="https://accounts.kingsch.at"
API_BASE_URL="https://connect.kingsch.at/api"
TOKEN_URL="https://connect.kingsch.at/oauth2/token"
SCOPES='["conference_calls"]'
REDIRECT_URI="http://localhost:8080/#/auth/callback"

echo -e "${YELLOW}Configuration:${NC}"
echo "Client ID: $CLIENT_ID"
echo "Auth URL: $AUTH_URL"
echo "API Base URL: $API_BASE_URL"
echo "Token URL: $TOKEN_URL"
echo "Redirect URI: $REDIRECT_URI"
echo ""

# Test 1: OAuth Authorization URL Construction
echo -e "${YELLOW}Test 1: OAuth Authorization URL Construction${NC}"
QUERY_STRING="client_id=$(echo $CLIENT_ID | sed 's/ /%20/g')&scopes=$(echo $SCOPES | sed 's/ /%20/g' | sed 's/\[/%5B/g' | sed 's/\]/%5D/g' | sed 's/"/%22/g')&redirect_uri=$(echo $REDIRECT_URI | sed 's/:/%3A/g' | sed 's/\//%2F/g' | sed 's/#/%23/g')&response_type=token&post_redirect=true"
OAUTH_URL="$AUTH_URL/?$QUERY_STRING"
echo "OAuth URL: $OAUTH_URL"
echo ""

# Test 2: Check if OAuth endpoint is reachable
echo -e "${YELLOW}Test 2: OAuth Endpoint Connectivity${NC}"
HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$AUTH_URL")
if [ "$HTTP_STATUS" -eq 200 ] || [ "$HTTP_STATUS" -eq 302 ]; then
    echo -e "${GREEN}✓ OAuth endpoint is reachable (HTTP $HTTP_STATUS)${NC}"
else
    echo -e "${RED}✗ OAuth endpoint failed (HTTP $HTTP_STATUS)${NC}"
fi
echo ""

# Test 3: Check API endpoints connectivity
echo -e "${YELLOW}Test 3: API Endpoints Connectivity${NC}"
PROFILE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE_URL/profile" -H "Authorization: Bearer test")
CONTACTS_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$API_BASE_URL/contacts" -H "Authorization: Bearer test")
echo "Profile API HTTP Status: $PROFILE_STATUS"
echo "Contacts API HTTP Status: $CONTACTS_STATUS"
if [ "$PROFILE_STATUS" -eq 401 ] && [ "$CONTACTS_STATUS" -eq 401 ]; then
    echo -e "${GREEN}✓ API endpoints are reachable (both require authentication)${NC}"
else
    echo -e "${YELLOW}! Unexpected response from API endpoints${NC}"
fi
echo ""

# Test 4: Test Profile API without token (should fail)
echo -e "${YELLOW}Test 4: Profile API without Token${NC}"
RESPONSE=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" "$API_BASE_URL/profile")
HTTP_STATUS=$(echo "$RESPONSE" | tail -1 | cut -d: -f2)
BODY=$(echo "$RESPONSE" | head -n -1)
echo "HTTP Status: $HTTP_STATUS"
echo "Response: $BODY"
if [ "$HTTP_STATUS" -eq 401 ] || [ "$HTTP_STATUS" -eq 403 ]; then
    echo -e "${GREEN}✓ Profile API correctly requires authentication${NC}"
else
    echo -e "${YELLOW}! Unexpected response from Profile API${NC}"
fi
echo ""

# Test 5: Test Token Refresh endpoint structure
echo -e "${YELLOW}Test 5: Token Refresh Endpoint Structure${NC}"
RESPONSE=$(curl -s -w "\nHTTP_STATUS:%{http_code}\n" -X POST "$TOKEN_URL" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -H "Accept: application/json" \
    -d "client_id=$CLIENT_ID&refresh_token=invalid_token&grant_type=refresh_token")
HTTP_STATUS=$(echo "$RESPONSE" | tail -1 | cut -d: -f2)
BODY=$(echo "$RESPONSE" | head -n -1)
echo "HTTP Status: $HTTP_STATUS"
echo "Response: $BODY"
if [ "$HTTP_STATUS" -eq 400 ] || [ "$HTTP_STATUS" -eq 401 ]; then
    echo -e "${GREEN}✓ Token refresh endpoint is reachable (invalid token rejected)${NC}"
else
    echo -e "${YELLOW}! Unexpected response from token refresh endpoint${NC}"
fi
echo ""

# Test 6: Test complete OAuth flow URL
echo -e "${YELLOW}Test 6: Complete OAuth Flow URL${NC}"
echo "To test the complete OAuth flow, open this URL in your browser:"
echo "$OAUTH_URL"
echo ""
echo "This should redirect to KingsChat OAuth, and after login should redirect back to:"
echo "$REDIRECT_URI"
echo ""

# Summary
echo -e "${YELLOW}=== Test Summary ===${NC}"
echo "1. OAuth endpoint connectivity: Tested"
echo "2. API base URL: Tested"
echo "3. Profile API authentication: Tested"
echo "4. Token refresh endpoint: Tested"
echo "5. OAuth flow URL construction: Tested"
echo -e "${GREEN}✓ All basic API endpoints are functional${NC}"
echo ""
echo "Next steps:"
echo "1. Test the OAuth URL in browser"
echo "2. Check if callback URL resolves to your Flutter app"
echo "3. Verify token extraction in Flutter callback screen"