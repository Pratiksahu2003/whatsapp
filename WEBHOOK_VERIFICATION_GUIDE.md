# Webhook Verification Troubleshooting Guide

## Common Error: "The callback URL or verify token couldn't be validated"

This error occurs when Meta cannot verify your webhook endpoint. Follow these steps to fix it:

### Step 1: Verify Your Verify Token

1. Go to your Settings page in the application
2. Find the "Verify Token" field
3. If empty, click "Generate" to create a new token
4. **Copy the token EXACTLY** - no extra spaces before or after

### Step 2: Configure in Meta Business Manager

1. Go to [Meta Business Manager](https://business.facebook.com/)
2. Navigate to: **WhatsApp** → **Configuration** → **Webhooks**
3. Set **Callback URL**: `https://www.tytil.store/whatsapp/webhook`
4. Set **Verify Token**: Paste the token from Step 1 (EXACTLY as shown)
5. Click **"Verify and Save"**

### Step 3: Check Your Logs

Check your Laravel logs at `storage/logs/laravel.log` for entries like:
- `Webhook verification attempt` - Shows what Meta sent
- `Webhook verification successful` - Success!
- `Webhook verification failed` - Shows why it failed

### Step 4: Test Your Endpoint

You can test the verification endpoint manually:

```
https://www.tytil.store/whatsapp/verify?hub_mode=subscribe&hub_verify_token=YOUR_TOKEN&hub_challenge=test123
```

Replace `YOUR_TOKEN` with your actual verify token. It should return `test123` if successful.

### Common Issues and Solutions

#### Issue 1: Token Mismatch
**Symptoms:** Logs show "No user found with matching verify token"
**Solution:**
- Make sure token matches EXACTLY (case-sensitive)
- No extra spaces before or after
- Copy directly from Settings page

#### Issue 2: Missing Parameters
**Symptoms:** Logs show "Missing token or challenge"
**Solution:**
- Meta sends: `hub_mode`, `hub_verify_token`, `hub_challenge`
- Check that all three are present in the request

#### Issue 3: Wrong Mode
**Symptoms:** Logs show "Invalid mode"
**Solution:**
- Meta must send `hub_mode=subscribe`
- If you see a different mode, Meta configuration might be wrong

#### Issue 4: Server Configuration
**Symptoms:** 403 error but logs show nothing
**Solution:**
- Check `.htaccess` or server config isn't blocking requests
- Ensure endpoint is publicly accessible (no auth required)
- Check firewall rules

#### Issue 5: Response Format
**Symptoms:** Meta says verification failed but logs show success
**Solution:**
- Endpoint must return ONLY the challenge string (plain text)
- Status code must be 200
- No JSON, no extra text

### Diagnostic Tools

1. **Test Webhook Configuration:**
   Visit: `https://www.tytil.store/whatsapp/test-webhook` (requires login)
   Shows your current configuration

2. **Verify Diagnostics:**
   Visit: `https://www.tytil.store/whatsapp/verify-diagnostics?token=YOUR_TOKEN` (requires login)
   Shows token comparison details

### Still Having Issues?

1. Check logs: `storage/logs/laravel.log`
2. Verify token in database matches exactly
3. Test endpoint manually with curl:
   ```bash
   curl "https://www.tytil.store/whatsapp/verify?hub_mode=subscribe&hub_verify_token=YOUR_TOKEN&hub_challenge=test123"
   ```
4. Ensure server is accessible from Meta's servers
5. Check SSL certificate is valid

### Meta's Requirements

According to Meta documentation:
- Endpoint must accept GET requests
- Must check `hub_mode === 'subscribe'`
- Must verify `hub_verify_token` matches stored token
- Must return `hub_challenge` as plain text (200 status)
- Must return 403 if verification fails

