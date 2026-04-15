@echo off
echo Building Tailwind CSS for production...

REM Check if npm is installed
npm --version >nul 2>&1
if %errorlevel% neq 0 (
    echo Error: npm is not installed. Please install Node.js from https://nodejs.org/
    echo Then run: npm install
    echo Then run this script again.
    pause
    exit /b 1
)

REM Install dependencies if node_modules doesn't exist
if not exist "node_modules" (
    echo Installing dependencies...
    npm install
)

REM Build CSS for production
echo Building CSS...
npx tailwindcss -i ./assets/css/src/main.css -o ./assets/css/style.css --minify

if %errorlevel% equ 0 (
    echo.
    echo ✅ Tailwind CSS built successfully for production!
    echo The compiled CSS is now in assets/css/style.css
    echo.
    echo For development with auto-rebuild, run:
    echo npm run build-css
) else (
    echo.
    echo ❌ Build failed. Please check the error messages above.
)

echo.
pause