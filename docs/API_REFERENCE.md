# Academia World - API Reference

## Overview

Academia World provides a comprehensive RESTful API for academic event management, networking, and resource sharing. This document provides detailed information about all available endpoints, authentication, and usage examples.

## Base URL

- **Development**: `http://localhost:8000/api/v1`
- **Production**: `https://your-domain.com/api/v1`

## Authentication

All protected endpoints require authentication using Laravel Sanctum tokens.

### Obtaining a Token
```bash
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@university.edu",
  "password": "password"
}
```

### Using the Token
Include the token in the Authorization header:
```bash
Authorization: Bearer your-api-token
```

## File Upload Support

The API supports two file upload methods:

1. **Multipart Form Data** (Traditional)
2. **Base64 JSON** (Modern/Mobile-friendly)

### Multipart Form Data
```bash
curl -X POST /api/v1/events/{event}/poster \
  -H "Authorization: Bearer token" \
  -F "poster=@image.jpg"
```

### Base64 JSON
```bash
curl -X POST /api/v1/events/{event}/poster \
  -H "Authorization: Bearer token" \
  -H "Content-Type: application/json" \
  -d '{"poster": "data:image/jpeg;base64,/9j/4AAQ..."}'
```

## Event Management

### Event Poster Management

#### Upload Event Poster
```
POST /api/v1/events/{event}/poster
```

**Authorization**: Event host or admin only

**Request Body** (Multipart):
- `poster` (file): Image file (jpeg, png, jpg, gif, max 2MB)

**Request Body** (JSON):
```json
{
  "poster": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}
```

**Response** (200):
```json
{
  "message": "Poster uploaded successfully",
  "data": {
    "poster_url": "https://domain.com/storage/event-posters/event_poster_123_1640995200.jpg"
  }
}
```

**Features**:
- Automatic image resizing to 800x600
- Quality optimization (90%)
- Automatic replacement of existing poster
- Support for multiple storage providers (local, S3, Cloudinary)

#### Update Event Poster
```
PUT /api/v1/events/{event}/poster
```

**Authorization**: Event host or admin only

**Note**: This endpoint completely replaces the existing poster with a new one. You must provide a new poster file.

**Request Body** (Multipart):
- `poster` (file): New image file (jpeg, png, jpg, gif, max 2MB)

**Request Body** (JSON):
```json
{
  "poster": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQ..."
}
```

**Response** (200):
```json
{
  "message": "Poster updated successfully",
  "data": {
    "poster_url": "https://domain.com/storage/event-posters/event_poster_123_1640995200.jpg"
  }
}
```

**cURL Example** (Multipart):
```bash
curl -X PUT /api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-api-token" \
  -F "poster=@new_poster.jpg"
```

**cURL Example** (JSON):
```bash
curl -X PUT /api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-api-token" \
  -H "Content-Type: application/json" \
  -d '{"poster": "data:image/jpeg;base64,/9j/4AAQ..."}'
```

**Important**: The `poster` field is required for updates. If you want to remove a poster without replacing it, use the DELETE endpoint instead.

#### Delete Event Poster
```
DELETE /api/v1/events/{event}/poster
```

**Authorization**: Event host or admin only

**Response** (200):
```json
{
  "message": "Poster deleted successfully"
}
```

**Response** (404):
```json
{
  "message": "No poster found for this event"
}
```

## Resource Management

### Upload Event Resource
```
POST /api/v1/events/{event}/resources
```

**Authorization**: Event host or admin only

**Request Body** (Multipart):
- `file` (file): Resource file (max 50MB)
- `title` (string, optional): Resource title
- `description` (string, optional): Resource description
- `resource_type` (string): One of: presentation, paper, recording, agenda, other
- `is_public` (boolean): Whether resource is publicly accessible
- `is_downloadable` (boolean): Whether resource can be downloaded
- `requires_registration` (boolean): Whether registration is required to access

**Request Body** (JSON with Base64):
```json
{
  "file": "data:application/pdf;base64,JVBERi0xLjQK...",
  "title": "Conference Presentation",
  "description": "Main keynote slides",
  "resource_type": "presentation",
  "is_public": true,
  "is_downloadable": true,
  "requires_registration": false
}
```

**Response** (201):
```json
{
  "message": "Resource uploaded successfully",
  "data": {
    "uuid": "123e4567-e89b-12d3-a456-426614174000",
    "title": "Conference Presentation",
    "description": "Main keynote slides",
    "original_filename": "presentation.pdf",
    "file_type": "pdf",
    "file_size": 2048576,
    "file_size_formatted": "2.0 MB",
    "file_url": "https://domain.com/storage/event-resources/resource_15_uuid.pdf",
    "resource_type": "presentation",
    "is_public": true,
    "is_downloadable": true,
    "requires_registration": false,
    "created_at": "2025-01-01T12:00:00.000000Z"
  }
}
```

**Supported File Types**:
- Documents: PDF, DOC, DOCX, PPT, PPTX, XLS, XLSX, TXT
- Images: JPEG, PNG, GIF
- Videos: MP4, AVI, MOV
- Audio: MP3, WAV
- Archives: ZIP, RAR

### List Event Resources
```
GET /api/v1/events/{event}/resources
```

**Query Parameters**:
- `type` (string, optional): Filter by resource type
- `public_only` (boolean, optional): Show only public resources

**Response** (200):
```json
{
  "message": "Event resources retrieved successfully",
  "data": [
    {
      "uuid": "123e4567-e89b-12d3-a456-426614174000",
      "title": "Conference Presentation",
      "description": "Main keynote slides",
      "original_filename": "presentation.pdf",
      "file_type": "pdf",
      "file_size": 2048576,
      "file_size_formatted": "2.0 MB",
      "file_url": "https://domain.com/storage/event-resources/resource_15_uuid.pdf",
      "resource_type": "presentation",
      "is_public": true,
      "is_downloadable": true,
      "download_count": 25,
      "view_count": 150,
      "uploaded_by": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe"
      },
      "created_at": "2025-01-01T12:00:00.000000Z"
    }
  ]
}
```

### Download Resource
```
GET /api/v1/resources/{resource}/download
```

**Authorization**: Based on resource permissions

**Response**:
- **URL Resources**: 302 redirect to file URL
- **Local Resources**: 200 with file download
- **Access Denied**: 403 with error message
- **Not Found**: 404 with error message

**Features**:
- Automatic download tracking
- Permission-based access control
- Support for all storage providers

### Update Resource Metadata
```
PUT /api/v1/resources/{resource}
```

**Authorization**: Event host or admin only

**Request Body**:
```json
{
  "title": "Updated Title",
  "description": "Updated description",
  "resource_type": "paper",
  "is_public": false,
  "is_downloadable": true,
  "requires_registration": true
}
```

**Response** (200):
```json
{
  "message": "Resource updated successfully",
  "data": {
    "uuid": "123e4567-e89b-12d3-a456-426614174000",
    "title": "Updated Title",
    "description": "Updated description",
    "file_url": "https://domain.com/storage/event-resources/resource_15_uuid.pdf",
    "resource_type": "paper",
    "is_public": false,
    "is_downloadable": true,
    "requires_registration": true,
    "updated_at": "2025-01-01T12:30:00.000000Z"
  }
}
```

### Delete Resource
```
DELETE /api/v1/resources/{resource}
```

**Authorization**: Event host or admin only

**Response** (200):
```json
{
  "message": "Resource deleted successfully"
}
```

**Features**:
- Soft delete (preserves database record)
- Physical file deletion from storage
- Audit logging

## Error Handling

### Standard Error Response
```json
{
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### Common HTTP Status Codes
- `200` - Success
- `201` - Created
- `302` - Redirect (for downloads)
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

### Common Issues and Solutions

#### Poster Upload/Update Issues

**Problem**: "The poster field is required" error
```json
{
  "message": "Validation errors",
  "errors": {
    "poster": ["The poster field is required."]
  }
}
```

**Solutions**:
1. **Multipart Form Data**: Ensure you're sending the file with the correct field name:
   ```bash
   curl -X POST /api/v1/events/{event}/poster \
     -H "Authorization: Bearer token" \
     -F "poster=@image.jpg"  # ← Field name must be "poster"
   ```

2. **JSON Base64**: Ensure the JSON structure is correct:
   ```bash
   curl -X POST /api/v1/events/{event}/poster \
     -H "Authorization: Bearer token" \
     -H "Content-Type: application/json" \
     -d '{"poster": "data:image/jpeg;base64,..."}'  # ← Must include data URI prefix
   ```

3. **File Size**: Ensure the image is under 2MB
4. **File Type**: Only jpeg, png, jpg, gif are supported

#### Resource Upload Issues

**Problem**: Validation errors for boolean fields
```json
{
  "errors": {
    "is_public": ["The is public field must be true or false."],
    "resource_type": ["The selected resource type is invalid."]
  }
}
```

**Solutions**:
1. **Boolean Values**: Use actual boolean values or strings:
   ```json
   {
     "is_public": true,        // ✓ Correct
     "is_downloadable": "true", // ✓ Also correct
     "requires_registration": false  // ✓ Correct
   }
   ```

2. **Resource Type**: Must be one of: `presentation`, `paper`, `recording`, `agenda`, `other`

3. **Multipart Form Data**: Use string values for booleans:
   ```bash
   curl -F "is_public=true" -F "resource_type=presentation" ...
   ```

## Rate Limiting

API endpoints are rate-limited to prevent abuse:

- **General endpoints**: 60 requests per minute
- **File upload endpoints**: 10 requests per minute
- **Authentication endpoints**: 5 requests per minute

## File Storage

### Storage Providers

The API supports multiple storage providers with automatic fallback:

1. **AWS S3** (Primary for production)
2. **Cloudinary** (Image optimization)
3. **Local Storage** (Development/fallback)

### Configuration

Storage provider is selected automatically based on environment variables:

```env
# AWS S3
AWS_ACCESS_KEY_ID=your_key
AWS_SECRET_ACCESS_KEY=your_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your_bucket

# Cloudinary
CLOUDINARY_CLOUD_NAME=your_cloud
CLOUDINARY_API_KEY=your_key
CLOUDINARY_API_SECRET=your_secret

# File Storage Driver
FILE_STORAGE_DRIVER=auto  # auto, s3, cloudinary, local
```

### File URL Format

All file URLs are stored as complete URLs in the database:

- **Local**: `https://domain.com/storage/path/file.ext`
- **S3**: `https://s3.amazonaws.com/bucket/path/file.ext`
- **Cloudinary**: `https://res.cloudinary.com/cloud/path/file.ext`

## SDK and Client Libraries

### JavaScript/TypeScript
```javascript
// Example with fetch
const response = await fetch('/api/v1/events/123/poster', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ' + token,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    poster: 'data:image/jpeg;base64,...'
  })
});
```

### cURL Examples

See the individual endpoint documentation above for comprehensive cURL examples.

## Testing the API

### Testing Poster Upload/Update

To test the poster endpoints, you can use these examples:

#### Test with a Real Image File
```bash
# Create a test image (Linux/Mac)
convert -size 100x100 xc:red test_poster.jpg

# Upload poster
curl -X POST http://localhost:8000/api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-token" \
  -F "poster=@test_poster.jpg"

# Update poster
curl -X PUT http://localhost:8000/api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-token" \
  -F "poster=@test_poster.jpg"
```

#### Test with Base64
```bash
# Convert image to base64 (Linux/Mac)
BASE64_IMAGE=$(base64 -w 0 test_poster.jpg)

# Upload via JSON
curl -X POST http://localhost:8000/api/v1/events/{event_uuid}/poster \
  -H "Authorization: Bearer your-token" \
  -H "Content-Type: application/json" \
  -d "{\"poster\": \"data:image/jpeg;base64,$BASE64_IMAGE\"}"
```

### Debugging Tips

1. **Check Authentication**: Ensure your token is valid and you're the event host
2. **Verify Event UUID**: Make sure the event exists and you have permission
3. **Content-Type Headers**: 
   - Multipart: Don't set Content-Type (let curl set it)
   - JSON: Set `Content-Type: application/json`
4. **File Size**: Maximum 2MB for images
5. **Base64 Format**: Must include the data URI prefix (`data:image/jpeg;base64,`)

### Common Issues & Solutions

#### "The poster field is required" Error
This error can occur when:
- Using JSON format but sending multipart headers
- Base64 string is malformed or missing the data URI prefix
- File upload is corrupted or invalid

**Solution**: 
- For multipart uploads: Use `multipart/form-data` and `-F` flag in curl
- For JSON uploads: Use `application/json` and proper base64 format with data URI
- Ensure the image is valid and under 2MB

**Example Fix**:
```bash
# Wrong - mixing formats
curl -X PUT /api/v1/events/{event}/poster \
  -H "Content-Type: application/json" \
  -F "poster=@image.jpg"  # This won't work

# Correct - multipart
curl -X PUT /api/v1/events/{event}/poster \
  -H "Authorization: Bearer token" \
  -F "poster=@image.jpg"

# Correct - JSON
curl -X PUT /api/v1/events/{event}/poster \
  -H "Authorization: Bearer token" \
  -H "Content-Type: application/json" \
  -d '{"poster": "data:image/jpeg;base64,..."}'
```

## Changelog

### v1.2.0 (Latest)
- Added event poster management endpoints
- Enhanced resource management with public URLs
- Improved file upload with dual format support
- Added download tracking and analytics

### v1.1.0
- Resource management endpoints
- File upload and storage integration
- Download permissions and access control

### v1.0.0
- Initial API release
- Basic event management
- User authentication and profiles
- Forum and discussion features
