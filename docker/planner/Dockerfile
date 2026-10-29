FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1

COPY requirements.txt requirements-dev.txt /tmp/
# docker-compose.yml sets it for development, where pytest runs in this container.
ARG DEV_DEPENDENCIES=false
RUN pip install --no-cache-dir -r /tmp/requirements.txt \
    && if [ "$DEV_DEPENDENCIES" = "true" ]; then pip install --no-cache-dir -r /tmp/requirements-dev.txt; fi

ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} appuser \
    && useradd -u ${UID} -g appuser -m appuser

USER appuser

WORKDIR /app
COPY --chown=appuser:appuser . /app

EXPOSE 8000
CMD ["python", "-m", "roster.server"]
