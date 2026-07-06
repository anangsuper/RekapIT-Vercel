const https = require('https');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const credsPath = 'c:\\Users\\MIS & IT\\Downloads\\RekapIT-Vercel-main\\config\\service-account.json';
const spreadsheetId = '19V_qkLtFmaH93dA9h1ZqM2T7JqZ6H_IEI94PVkPnNIc';

async function getAccessToken() {
    const creds = JSON.parse(fs.readFileSync(credsPath, 'utf8'));
    const header = Buffer.from(JSON.stringify({ alg: 'RS256', typ: 'JWT' })).toString('base64url');
    const now = Math.floor(Date.now() / 1000);
    const payload = Buffer.from(JSON.stringify({
        iss: creds.client_email,
        scope: 'https://www.googleapis.com/auth/spreadsheets',
        aud: 'https://oauth2.googleapis.com/token',
        exp: now + 3600,
        iat: now
    })).toString('base64url');
    
    const signatureInput = `${header}.${payload}`;
    const sign = crypto.createSign('SHA256');
    sign.update(signatureInput);
    const signature = sign.sign(creds.private_key).toString('base64url');
    const jwt = `${signatureInput}.${signature}`;
    
    return new Promise((resolve, reject) => {
        const req = https.request('https://oauth2.googleapis.com/token', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            }
        }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                const data = JSON.parse(body);
                if (data.access_token) {
                    resolve(data.access_token);
                } else {
                    reject(new Error("Failed to get token: " + body));
                }
            });
        });
        req.on('error', reject);
        req.write(`grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion=${jwt}`);
        req.end();
    });
}

function apiCall(token, url, method, data = null) {
    return new Promise((resolve, reject) => {
        const req = https.request(url, {
            method: method,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        }, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    resolve(body ? JSON.parse(body) : {});
                } else {
                    reject(new Error(`API Error ${res.statusCode}: ${body}`));
                }
            });
        });
        req.on('error', reject);
        if (data) {
            req.write(JSON.stringify(data));
        }
        req.end();
    });
}

async function main() {
    try {
        console.log("Authenticating...");
        const token = await getAccessToken();
        console.log("Authentication successful!");
        
        console.log("Fetching users sheet...");
        const sheetData = await apiCall(
            token,
            `https://sheets.googleapis.com/v4/spreadsheets/${spreadsheetId}/values/users!A1:Z100`,
            'GET'
        );
        
        const values = sheetData.values;
        if (!values || values.length <= 1) {
            console.log("No users found.");
            return;
        }
        
        console.log("Current sheet rows:");
        values.forEach((row, i) => console.log(`Row ${i + 1}:`, row));
        
        // Find if there is duplicate IDs
        let idMap = {};
        let modified = false;
        let nextId = 1;
        
        // Find the maximum ID first
        values.forEach((row, i) => {
            if (i === 0) return; // skip header
            const idVal = parseInt(row[0]);
            if (!isNaN(idVal) && idVal > nextId) {
                nextId = idVal;
            }
        });
        nextId += 1;
        
        console.log(`Suggested next unique ID: ${nextId}`);
        
        for (let i = 1; i < values.length; i++) {
            const idVal = values[i][0];
            if (idMap[idVal]) {
                console.log(`Duplicate found on Row ${i + 1}: ID ${idVal} for user '${values[i][1]}'`);
                console.log(`Changing ID from ${idVal} to ${nextId}`);
                values[i][0] = nextId.toString();
                idMap[nextId] = true;
                nextId += 1;
                modified = true;
            } else {
                idMap[idVal] = true;
            }
        }
        
        if (modified) {
            console.log("Updating spreadsheet values...");
            await apiCall(
                token,
                `https://sheets.googleapis.com/v4/spreadsheets/${spreadsheetId}/values/users!A1:Z100?valueInputOption=USER_ENTERED`,
                'PUT',
                { values: values }
            );
            console.log("Spreadsheet successfully updated!");
        } else {
            console.log("No duplicate IDs found. No updates needed.");
        }
        
    } catch (e) {
        console.error("Error:", e);
    }
}

main();
