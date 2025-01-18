<?php

const 
    HTTP_OK = 200, 
    HTTP_CREATED = 201,
    HTTP_MOVED_PERMANENTLY = 301,
    HTTP_NOT_MODIFIED = 304,
    HTTP_MOVED_TEMPORARILY = 307,
    HTTP_BAD_REQUEST = 400,
    HTTP_UNAUTHORIZED = 401,
    HTTP_FORBIDDEN = 403,
    HTTP_NOT_FOUND = 404,
    HTTP_METHOD_NOT_ALLOWED = 405,
    HTTP_NOT_ACCEPTABLE = 406,
    HTTP_CONFLICT = 409,
    HTTP_TEAPOT = 418,
    HTTP_UNPROCESSABLE = 422,
    HTTP_TOO_MANY_REQUESTS = 429,
    HTTP_INTERNAL_ERROR = 500,
    HTTP_NOT_IMPLEMENTED = 501;

function GetStatusMessage(int $code): string
{
    return match($code)
    {
        // successful
        HTTP_OK => "Operation successful",
        HTTP_CREATED => "Object created successfully",

        HTTP_MOVED_PERMANENTLY => "Moved permanently",

        // Response if client cache is valid
        HTTP_NOT_MODIFIED => "Not modified",

        HTTP_MOVED_TEMPORARILY => "Temporary redirect",

        // Invalid formatted request
        HTTP_BAD_REQUEST => "Bad request",

        // No authentication / authentication failed
        HTTP_UNAUTHORIZED => "Unauthorized",

        // Valid authentication, user does not have permission
        HTTP_FORBIDDEN => "Forbidden",

        // End point not valid (non-existent endpoint, or resource was deleted)
        HTTP_NOT_FOUND => "Not found",

        // URI valid, but wrong method
        HTTP_METHOD_NOT_ALLOWED => "Method Not Allowed",

        // Server cannot produce the desired content type for the endpoint
        HTTP_NOT_ACCEPTABLE => "Not Acceptable",

        // Typically when attempting to do something that violates business rules of current database state (ex. creating a resource that already exists)
        HTTP_CONFLICT => "Server state conflict",

        // Potential easter egg somewhere: denied because the server is brewing coffee.
        HTTP_TEAPOT => "I'm a teapot",

        // When basic syntax is correct, but further request validation fails
        HTTP_UNPROCESSABLE => "Unprocessable entity",

        // Request denied due to rate limit
        HTTP_TOO_MANY_REQUESTS => "Too many requests",

        // Something went wrong (admin can check error logs)
        HTTP_INTERNAL_ERROR => "Internal Server Error",

        // If the endpoint is clearly planned but not implemented
        HTTP_NOT_IMPLEMENTED => "Not Implemented",
    };
}