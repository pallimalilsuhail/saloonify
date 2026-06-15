#!/bin/bash

source .env

# Prompt user for app type (default: php)
read -p "Enter app type [php]: " APP_TYPE
APP_TYPE=${APP_TYPE:-php}

# Prompt user for namespace (default: main)
read -p "Enter namespace [main]: " NAMESPACE
NAMESPACE=${NAMESPACE:-main}

# Detect system architecture automatically
SYSTEM_ARCH=$(uname -m)
ALLOY_VERSION="v1.6.1"
case $SYSTEM_ARCH in
    x86_64)
        ARCHITECTURE="amd64"
        ALLOY_ZIP_URL="https://github.com/grafana/alloy/releases/download/${ALLOY_VERSION}/alloy-linux-amd64.zip"
        ALLOY_ZIP_FILE="alloy-linux-amd64.zip"
        ALLOY_BIN_NAME="alloy-linux-amd64"
        ;;
    aarch64|arm64)
        ARCHITECTURE="arm64"
        ALLOY_ZIP_URL="https://github.com/grafana/alloy/releases/download/${ALLOY_VERSION}/alloy-linux-arm64.zip"
        ALLOY_ZIP_FILE="alloy-linux-arm64.zip"
        ALLOY_BIN_NAME="alloy-linux-arm64"
        ;;
    *)
        echo "Unsupported architecture: $SYSTEM_ARCH"
        echo "Defaulting to amd64..."
        ARCHITECTURE="amd64"
        ALLOY_ZIP_URL="https://github.com/grafana/alloy/releases/download/${ALLOY_VERSION}/alloy-linux-amd64.zip"
        ALLOY_ZIP_FILE="alloy-linux-amd64.zip"
        ALLOY_BIN_NAME="alloy-linux-amd64"
        ;;
esac

echo "Configuration:"
echo "  App type: $APP_TYPE"
echo "  Namespace: $NAMESPACE"
echo "  System architecture detected: $SYSTEM_ARCH ($ARCHITECTURE)"
echo "  Alloy version: $ALLOY_VERSION"
echo ""

APP_DOMAIN=$(echo $APP_URL | awk -F[/:] '{print $4}')
LOG_DIR="/home/forge/$APP_DOMAIN/storage/logs"
ALLOY_DIR="/home/forge/$APP_DOMAIN/alloy"
CONFIG_FILE="$ALLOY_DIR/config.alloy"
ALLOY_BINARY="$ALLOY_DIR/alloy"
ALLOY_ZIP="$ALLOY_DIR/$ALLOY_ZIP_FILE"

if [ ! -d "$ALLOY_DIR" ]; then
    echo "Alloy directory not found. Creating $ALLOY_DIR..."
    mkdir -p "$ALLOY_DIR"
fi

if [ ! -f "$ALLOY_BINARY" ]; then
    echo "Alloy binary not found. Downloading and unzipping..."
    curl -L -o "$ALLOY_ZIP" "$ALLOY_ZIP_URL"
    unzip -o "$ALLOY_ZIP" -d "$ALLOY_DIR"
    mv "$ALLOY_DIR/$ALLOY_BIN_NAME" "$ALLOY_BINARY"
    chmod +x "$ALLOY_BINARY"
    rm "$ALLOY_ZIP"
    echo "Alloy has been set up at $ALLOY_BINARY."
else
    echo "Alloy binary already exists at $ALLOY_BINARY."
fi

if [ ! -f "$CONFIG_FILE" ]; then
    echo "Config file not found. Creating config.alloy..."

    cat <<EOL > "$CONFIG_FILE"
logging {
  level  = "info"
  format = "logfmt"
}

local.file_match "laravel_logs" {
  path_targets = [{
    __path__  = "$LOG_DIR/laravel.log",
    job       = "laravel",
    env       = "$APP_ENV",
    namespace = "$NAMESPACE",
    app       = "$APP_TYPE",
  }]
}

loki.source.file "laravel" {
  targets    = local.file_match.laravel_logs.targets
  forward_to = [loki.write.default.receiver]
}

loki.write "default" {
  endpoint {
    url = "$LOKI_URL"

    basic_auth {
      username = "$LOKI_USERNAME"
      password = "$LOKI_PASSWORD"
    }
  }
}
EOL
    echo "config.alloy has been created at $CONFIG_FILE."
else
    echo "config.alloy already exists at $CONFIG_FILE."
fi
