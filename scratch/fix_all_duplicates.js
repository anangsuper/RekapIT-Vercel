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
        console.log("Authenticating...");
        const token = await getAccessToken();
        console.log("Authentication successful!");
        
        // List of all sheets to check and repair
        const tables = [
            'cabang', 'divisi', 'kategori_aset', 'karyawan', 'users', 
            'assets', 'asset_history', 'maintenance', 'repairs', 
            'activity_logs', 'asset_mutations', 'audits', 'sparepart', 'penggunaan_sparepart'
        ];
        
        for (const table of tables) {
            console.log(`Checking table: ${table}...`);
            const sheetData = await apiCall(
                token,
                `https://sheets.googleapis.com/v4/spreadsheets/${spreadsheetId}/values/${table}!A1:Z1000`,
                'GET'
            );
            
            const values = sheetData.values;
            if (!values || values.length <= 1) {
                console.log(`Table ${table} has no data or header only.`);
                continue;
            }
            
            let idMap = {};
            let modified = false;
            let nextId = 1;
            
            // Find max ID
            values.forEach((row, i) => {
                if (i === 0) return; // skip header
                const idVal = parseInt(row[0]);
                if (!isNaN(idVal) && idVal > nextId) {
                    nextId = idVal;
                }
            });
            nextId += 1;
            
            for (let i = 1; i < values.length; i++) {
                const idVal = values[i][0];
                if (idVal === "" || idVal === null) {
                    // Assign a new ID if empty
                    console.log(`Empty ID found on Row ${i + 1} of ${table}. Assigning ID: ${nextId}`);
                    values[i][0] = nextId.toString();
                    idMap[nextId] = true;
                    nextId += 1;
                    modified = true;
                } else if (idMap[idVal]) {
                    // Resolve duplicate ID
                    console.log(`Duplicate ID ${idVal} found on Row ${i + 1} of ${table}. Changing to: ${nextId}`);
                    values[i][0] = nextId.toString();
                    idMap[nextId] = true;
                    nextId += 1;
                    modified = true;
                } else {
                    idMap[idVal] = true;
                }
            }
            
            if (modified) {
                console.log(`Updating table ${table} in Google Sheets...`);
                await apiCall(
                    token,
                    `https://sheets.googleapis.com/v4/spreadsheets/${spreadsheetId}/values/${table}!A1:Z1000?valueInputOption=USER_ENTERED`,
                    'PUT',
                    { values: values }
                );
                console.log(`Table ${table} successfully updated!`);
            } else {
                console.log(`Table ${table} has no duplicate or empty IDs.`);
            }
        }
        console.log("All tables checked and fixed!");
        
    } catch (e) {
        console.error("Error:", e);
    }
}

main();
