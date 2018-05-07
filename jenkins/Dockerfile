FROM jenkins/jenkins:latest

# Add here other plugins to be installed
RUN /usr/local/bin/install-plugins.sh git matrix-auth

ENV JENKINS_USER admin
ENV JENKINS_PASS admin

# Skip initial setup wizard
ENV JAVA_OPTS -Djenkins.install.runSetupWizard=false

# Create a standard admin user
COPY default-user.groovy /usr/share/jenkins/ref/init.groovy.d/
COPY --chown=jenkins:jenkins jobs /var/jenkins_home/jobs

VOLUME /var/jenkins_home
