#!/bin/sh
set -eu

APP_ROOT="/app"
CONFIG_PATH="${CONFIG_PATH:-${APP_ROOT}/profile/user/config/user.ini}"
PROFILE_ROOT="$(dirname "$(dirname "${CONFIG_PATH}")")"
EXAMPLE_PROFILE="${APP_ROOT}/profile/example"
DEPENDENCY_HASH_FILE="${APP_ROOT}/vendor/.composer-lock.hash"
INFO_LABEL="${Info:-[INFO]}"
FONT_RESET="${Font:-}"
GREEN_BG="${GreenBG:-}"
RED_BG="${RedBG:-}"

print_block() {
  printf '\n'
  printf ' ======== \n'
  printf ' %s %s %s \n' "${INFO_LABEL}" "$1" "${FONT_RESET}"
  printf ' ======== \n'
}

validate_branch() {
  case "${1}" in
    ''|*[!A-Za-z0-9._/-]*)
      print_block "${RED_BG} 无效的分支名称: ${1:-<empty>} ${FONT_RESET}"
      exit 1
      ;;
  esac
}

read_profile_branch() {
  if [ ! -f "${CONFIG_PATH}" ]; then
    return 0
  fi

  awk -F= '
    /^\[app\]/ { in_app=1; next }
    /^\[/ { in_app=0 }
    in_app && $1 ~ /^[[:space:]]*branch[[:space:]]*$/ {
      value=$2
      gsub(/^[[:space:]]+|[[:space:]]+$/, "", value)
      gsub(/"/, "", value)
      print value
      exit
    }
  ' "${CONFIG_PATH}"
}

resolve_branch() {
  if [ -n "${BRANCH:-}" ]; then
    echo "${BRANCH}"
    return
  fi

  profile_branch="$(read_profile_branch)"
  if [ -n "${profile_branch}" ]; then
    echo "${profile_branch}"
    return
  fi

  echo "master"
}

initialize_branch() {
  branch_name="$(resolve_branch)"
  validate_branch "${branch_name}"
  export BRANCH="${branch_name}"
  print_block "${GREEN_BG} 运行分支: ${branch_name} ${FONT_RESET}"
}

assert_profile_root_safe() {
  case "${CONFIG_PATH}" in
    *'..'*)
      print_block "${RED_BG} CONFIG_PATH 不允许包含 .. : ${CONFIG_PATH} ${FONT_RESET}"
      exit 1
      ;;
  esac

  case "${PROFILE_ROOT}" in
    "${APP_ROOT}/profile"|"${APP_ROOT}/profile/"|"/"|"/etc"|"/root"|"/app"|"/app/")
      print_block "${RED_BG} 非法的 profile 根目录: ${PROFILE_ROOT} ${FONT_RESET}"
      exit 1
      ;;
    "${APP_ROOT}/profile/"*)
      return
      ;;
    *)
      print_block "${RED_BG} profile 根目录必须位于 ${APP_ROOT}/profile 下: ${PROFILE_ROOT} ${FONT_RESET}"
      exit 1
      ;;
  esac
}

escape_ini_value() {
  printf '%s' "${1:-}" | tr -d '\r\n' | sed 's/\\/\\\\/g; s/"/\\"/g'
}

refresh_dependencies() {
  if [ ! -f "${APP_ROOT}/composer.lock" ]; then
    return
  fi

  current_hash="$(sha1sum "${APP_ROOT}/composer.lock" | awk '{print $1}')"
  stored_hash=""
  if [ -f "${DEPENDENCY_HASH_FILE}" ]; then
    stored_hash="$(cat "${DEPENDENCY_HASH_FILE}")"
  fi

  if [ ! -f "${APP_ROOT}/vendor/autoload.php" ] || [ "${current_hash}" != "${stored_hash}" ]; then
    print_block "${GREEN_BG} composer.lock 发生变化，正在同步依赖 ${FONT_RESET}"
    composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader
    mkdir -p "$(dirname "${DEPENDENCY_HASH_FILE}")"
    echo "${current_hash}" > "${DEPENDENCY_HASH_FILE}"
    return
  fi

  print_block "${GREEN_BG} 依赖无需更新 ${FONT_RESET}"
}

prepare_profile() {
  print_block "${GREEN_BG} 正在使用当前 profile 目录方案 ${FONT_RESET}"
  assert_profile_root_safe

  if [ ! -f "${CONFIG_PATH}" ]; then
    print_block "${GREEN_BG} 未检测到外部配置，正在生成默认 profile ${FONT_RESET}"
    mkdir -p "$(dirname "${PROFILE_ROOT}")"
    rm -rf "${PROFILE_ROOT}"
    cp -r "${EXAMPLE_PROFILE}" "${PROFILE_ROOT}"

    escaped_user="$(escape_ini_value "${USER_NAME:-}")"
    escaped_password="$(escape_ini_value "${USER_PASSWORD:-}")"
    escaped_branch="$(escape_ini_value "${BRANCH}")"
    awk -v username="${escaped_user}" -v password="${escaped_password}" -v branch="${escaped_branch}" '
      BEGIN { section = "" }
      /^\[.*\]$/ {
        section = $0
        print
        next
      }
      section == "[login_account]" && $0 ~ /^username[[:space:]]*=/ {
        print "username = \"" username "\""
        next
      }
      section == "[login_account]" && $0 ~ /^password[[:space:]]*=/ {
        print "password = \"" password "\""
        next
      }
      section == "[app]" && $0 ~ /^branch[[:space:]]*=/ {
        print "branch = " branch
        next
      }
      { print }
    ' "${CONFIG_PATH}" > "${CONFIG_PATH}.tmp"
    mv "${CONFIG_PATH}.tmp" "${CONFIG_PATH}"
  else
    print_block "${GREEN_BG} 正在使用外部配置文件 ${FONT_RESET}"
  fi
}

start_application() {
  if [ "${CAPTCHA:-0}" = "1" ]; then
    print_block "${GREEN_BG} 正在使用验证码服务 ${FONT_RESET}"
    printf ' ======== \n'
    printf ' %s %s %s \n' "${INFO_LABEL}" "${GREEN_BG} 验证码服务地址：http://${CAPTCHA_HOST}:${CAPTCHA_PORT} ${FONT_RESET}" "${FONT_RESET}"
    printf ' ======== \n'
    (
      cd "${APP_ROOT}/captcha"
      php -S "${CAPTCHA_HOST}:${CAPTCHA_PORT}"
    ) &
    php "${APP_ROOT}/app.php" m:a
    return
  fi

  php "${APP_ROOT}/app.php"
}

initialize_branch
refresh_dependencies
prepare_profile
start_application
