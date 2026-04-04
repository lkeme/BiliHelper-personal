#!/bin/sh
set -eu

APP_ROOT="/app"
CONFIG_PATH="${CONFIG_PATH:-${APP_ROOT}/profile/user/config/user.ini}"
PROFILE_ROOT="$(dirname "$(dirname "${CONFIG_PATH}")")"
EXAMPLE_PROFILE="${APP_ROOT}/profile/example"
DEPENDENCY_HASH_FILE="${APP_ROOT}/vendor/.composer-lock.hash"

print_block() {
  echo
  echo " ======== "
  echo " ${Info} ${1} ${Font} "
  echo " ======== "
}

resolve_remote_url() {
  case "${MIRRORS:-0}" in
    "custom")
      echo "${CUSTOM_CLONE_URL}"
      ;;
    "1")
      echo "https://ghfast.top/https://github.com/lkeme/BiliHelper-personal.git"
      ;;
    "2")
      echo "https://gitclone.com/github.com/lkeme/BiliHelper-personal.git"
      ;;
    "3")
      echo "https://gh-proxy.com/https://github.com/lkeme/BiliHelper-personal.git"
      ;;
    "4")
      echo "https://githubfast.com/lkeme/BiliHelper-personal.git"
      ;;
    "5")
      echo "https://hub.gitmirror.com/https://github.com/lkeme/BiliHelper-personal.git"
      ;;
    *)
      echo "https://github.com/lkeme/BiliHelper-personal.git"
      ;;
  esac
}

validate_branch() {
  case "${1}" in
    ''|*[!A-Za-z0-9._/-]*)
      print_block "${RedBG} 无效的分支名称: ${1:-<empty>} ${Font}"
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

sync_code() {
  branch_name="$(resolve_branch)"
  validate_branch "${branch_name}"
  export BRANCH="${branch_name}"

  if [ "${AUTO_UPDATE:-1}" != "1" ]; then
    print_block "${GreenBG} 代码分支: ${branch_name} ${Font}"
    print_block "${RedBG} 已跳过更新同步 ${Font}"
    return
  fi

  remote_url="$(resolve_remote_url)"
  print_block "${GreenBG} 代码分支: ${branch_name} ${Font}"
  git remote set-url origin "${remote_url}"

  print_block "${GreenBG} 正在同步代码分支 ${branch_name} ${Font}"
  git fetch --depth=1 origin "${branch_name}"
  git checkout -B "${branch_name}" "origin/${branch_name}"
  git reset --hard "origin/${branch_name}"
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
    print_block "${GreenBG} composer.lock 发生变化，正在同步依赖 ${Font}"
    composer install --no-interaction --no-progress --prefer-dist --optimize-autoloader
    mkdir -p "$(dirname "${DEPENDENCY_HASH_FILE}")"
    echo "${current_hash}" > "${DEPENDENCY_HASH_FILE}"
    return
  fi

  print_block "${GreenBG} 依赖无需更新 ${Font}"
}

prepare_profile() {
  print_block "${GreenBG} 正在使用当前 profile 目录方案 ${Font}"

  if [ ! -f "${CONFIG_PATH}" ]; then
    print_block "${GreenBG} 未检测到外部配置，正在生成默认 profile ${Font}"
    mkdir -p "$(dirname "${PROFILE_ROOT}")"
    rm -rf "${PROFILE_ROOT}"
    cp -r "${EXAMPLE_PROFILE}" "${PROFILE_ROOT}"

    sed -i "s/^username = \".*\"/username = \"${USER_NAME}\"/" "${CONFIG_PATH}"
    sed -i "s/^password = \".*\"/password = \"${USER_PASSWORD}\"/" "${CONFIG_PATH}"
    sed -i "s/^branch = .*/branch = ${BRANCH}/" "${CONFIG_PATH}"
  else
    print_block "${GreenBG} 正在使用外部配置文件 ${Font}"
  fi
}

start_application() {
  if [ "${CAPTCHA:-0}" = "1" ]; then
    print_block "${GreenBG} 正在使用验证码服务 ${Font}"
    echo " ======== "
    echo " ${Info} ${GreenBG} 验证码服务地址：http://${CAPTCHA_HOST}:${CAPTCHA_PORT} ${Font} "
    echo " ======== "
    (cd "${APP_ROOT}/captcha" && php -S "${CAPTCHA_HOST}:${CAPTCHA_PORT}" &) ; (php "${APP_ROOT}/app.php" m:a)
    return
  fi

  php "${APP_ROOT}/app.php"
}

sync_code
refresh_dependencies
prepare_profile
start_application
