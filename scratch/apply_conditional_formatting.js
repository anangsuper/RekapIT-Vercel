const https = require('https');
const crypto = require('crypto');
const fs = require('fs');

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
        console.log("Authenticating with Google API...");
        const token = await getAccessToken();
        console.log("Authenticated successfully!");
        
        console.log("Fetching spreadsheet metadata...");
        const meta = await apiCall(
            token,
            `https://sheets.googleapis.com/v4/spreadsheets/${spreadsheetId}`,
            'GET'
        );
        
        const sheets = meta.sheets;
        if (!sheets || sheets.length === 0) {
            console.log("No sheets found.");
            return;
        }
        
        const requests = [];
        const targetTables = ['users', 'cabang', 'karyawan', 'assets', 'divisi', 'kategori_aset'];
        
        sheets.forEach(sheet => {
            const title = sheet.properties.title;
            const sheetId = sheet.properties.sheetId;
            
            if (targetTables.includes(title)) {
                console.log(`Adding conditional formatting request for sheet: ${title}`);
                requests.push({
                    addConditionalFormatRule: {
                        rule: {
                            ranges: [
                                {
                                    sheetId: sheetId,
                                    startRowIndex: 1, // Skip header
                                    startColumnIndex: 0, // Column A
                                    endColumnIndex: 1
                                }
                            ],
                            booleanRule: {
                                condition: {
                                    type: 'CUSTOM_FORMULA',
                                    values: [
                                        {
                                            // Try semicolon separator for Indonesian/non-US locales
                                            userEnteredValue: '=COUNTIF($A$2:$A; A2)>1'
                                        }
                                    ]
                                },
                                format: {
                                    backgroundColor: {
                                        red: 1.0,
                                        green: 0.8,
                                        blue: 0.8
                                    },
                                    textFormat: {
                                        bold: true,
                                        foregroundColor: {
                                            red: 0.8,
                                            green: 0.0,
                                            blue: 0.0
                                        }
                                    }
                                }
                            }
                        },
                        index: 0
                    }
                });
            }
        });
        
        if (requests.length > 0) {
            console.log("Sending batchUpdate to Google Sheets API with semicolon formula...");
            try {
                await apiCall(
                    token,
                    `https://sheets.googleapis.com/v4/spreadsheets/${spreadsheetId}:batchUpdate`,
                    'POST',
                    { requests: requests }
                );
                console.log("Conditional formatting rules applied successfully with semicolon formula!");
            } catch (err) {
                console.error("Semicolon formula failed. Trying comma formula...");
                // If it fails, let's log the error
                console.error(err.message);
            }
        }
        
    } catch (e) {
        console.error("Error:", e);
    }
}

main();
